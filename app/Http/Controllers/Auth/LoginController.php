<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFunnel;
use App\Models\Funnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            Auth::user()?->forceFill([
                'last_login_at' => now(),
            ])->save();

            // If user clicked a shared funnel link before logging in, assign it now
            if ($pendingSlug = session()->pull('pending_funnel_slug')) {
                $funnel = Funnel::where('slug', $pendingSlug)->where('status', 'active')->first();
                if ($funnel) {
                    UserFunnel::withTrashed()->updateOrCreate(
                        ['user_id' => Auth::id(), 'funnel_id' => $funnel->id],
                        ['assigned_via' => 'share_link', 'assigned_at' => now(), 'deleted_at' => null]
                    );
                    return redirect()->to('/funnel/' . $pendingSlug);
                }
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function directLogin(Request $request)
    {
        try {
            $validated = $request->validate([
                'email'    => ['required', 'email'],
                'name'     => ['nullable', 'string', 'max:255'],
                'phone'    => ['nullable', 'string', 'max:20'],
                'password' => ['nullable', 'string', 'min:8'],
            ]);

            Log::channel('direct_login')->info('directLogin: request received', [
                'email' => $validated['email'],
                'ip'    => $request->ip(),
            ]);

            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name'     => $validated['name'] ?? '',
                    'phone'    => $validated['phone'] ?? '',
                    'password' => bcrypt(Str::random(32)),
                    'role'     => 'Admin'
                ]
            );

            Log::channel('direct_login')->info('directLogin: user found/created', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'created' => $user->wasRecentlyCreated,
                'ip'      => $request->ip(),
            ]);

            $loginUrl = URL::temporarySignedRoute(
                'sso.web.login',
                now()->addMinute(),
                ['user' => $user->id]
            );

            Log::channel('direct_login')->info('directLogin: signed URL generated', [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);

            return response()->json([
                'success'   => true,
                'message'   => $user->wasRecentlyCreated
                    ? 'User created successfully'
                    : 'User already exists',
                'user_id'   => $user->id,
                'login_url' => $loginUrl,
            ]);

        } catch (ValidationException $e) {
            Log::channel('direct_login')->warning('directLogin: validation failed', [
                'email'  => $request->input('email'),
                'errors' => $e->errors(),
                'ip'     => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::channel('direct_login')->error('directLogin: unexpected error', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
                'file'  => $e->getFile(),
                'ip'    => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again.',
            ], 500);
        }
    }

    public function ssoWebLogin(Request $request, $user)
    {
        try {
            Log::channel('sso_login')->info('ssoWebLogin: request received', [
                'user_id' => $user,
                'ip'      => $request->ip(),
            ]);

            $user = User::findOrFail($user);

            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            $user->forceFill(['last_login_at' => now()])->save();

            Log::channel('sso_login')->info('ssoWebLogin: user logged in successfully', [
                'user_id'       => $user->id,
                'email'         => $user->email,
                'last_login_at' => $user->last_login_at,
                'ip'            => $request->ip(),
            ]);

            return redirect()->route('dashboard');

        } catch (\Exception $e) {
            Log::channel('sso_login')->error('ssoWebLogin: unexpected error', [
                'user_id' => is_object($user) ? $user->id : $user,
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
                'ip'      => $request->ip(),
            ]);

            return redirect()->route('login')->withErrors(['error' => 'Login failed. Please try again.']);
        }
    }
}
