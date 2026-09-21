<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\ChatSession;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Auth::viaRequest('chat-token', function ($request) {
            $token = $request->bearerToken();

            if (!$token) {
                return null;
            }

            $session = ChatSession::with('chatUser')
                ->where('token_hash', hash('sha256', $token))
                ->where('expires_at', '>', now())
                ->first();

            if (!$session) {
                return null;
            }

            /*
             * SLIDING EXPIRY: the window is "60 minutes of INACTIVITY", not "60 minutes from
             * sign-in". Every authenticated request pushes expires_at forward, so a patient in
             * a running conversation is never cut off mid-exchange; the token only lapses once
             * they have genuinely stopped, at which point the app asks them to open the
             * conversation again (which returns the same thread, history intact).
             *
             * This deliberately matches CHAT_LOCK_MINUTES in meaning, so there is ONE idea of
             * "this conversation has gone quiet" rather than two clocks measuring from
             * different events.
             *
             * Extended on every request rather than only past a halfway point: a threshold is
             * cheaper in writes but can expire a session after LESS than the full window (quiet
             * at minute 29, back at minute 61 — under an hour, yet dead), which is the exact
             * interruption this exists to prevent. Patient traffic is low — identify, list,
             * open, send — because patients receive messages over Reverb rather than polling.
             */
            $session->forceFill([
                'expires_at' => now()->addMinutes((int) config('chat.session_minutes', 60)),
            ])->save();

            return $session->chatUser;
        });
    }
}
