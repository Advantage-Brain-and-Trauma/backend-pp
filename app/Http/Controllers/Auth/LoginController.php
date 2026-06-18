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
            $request->validate([
                'email' => ['required', 'email'],
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                Log::warning('directLogin: user not found', ['email' => $request->email]);
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            $loginUrl = URL::temporarySignedRoute(
                'sso.web.login',
                now()->addMinute(),
                ['user' => $user->id]
            );

            Log::info('directLogin: signed URL generated', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'success' => true,
                'login_url' => $loginUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('directLogin: unexpected error', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
            $user = User::findOrFail($user);

            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            Log::info('ssoWebLogin: user logged in successfully', ['user_id' => $user->id, 'email' => $user->email]);

            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            Log::error('ssoWebLogin: unexpected error', [
                'user' => $user,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('login')->withErrors(['error' => 'Login failed. Please try again.']);
        }
    }
}
