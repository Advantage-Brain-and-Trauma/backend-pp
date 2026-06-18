<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserFunnel;
use App\Models\Funnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function directLogin(Request $request){
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();
        if ($user) {
            Auth::guard('web')->login($user);
            return redirect()->intended(route('dashboard'));
        } else {
            return response()->json(['message' => 'User not found'], 404);
        }
    }
}
