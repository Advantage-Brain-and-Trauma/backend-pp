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

            return $session?->chatUser;
        });
    }
}
