<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(2000)->by($request->user()?->id ?: $request->ip());
        });

        /*
         * Chat limiters — keyed on IDENTITY, not on the request IP.
         *
         * A plain `throttle:n,1` keys on $request->user()?->id ?: ip(). Neither chat route
         * group populates the default guard's user, so both would fall back to the IP and
         * share ONE bucket:
         *   - every Medhiwa staff member polls through a single server IP, so one cap would
         *     be divided across the whole staff body, and
         *   - patients behind one clinic or household NAT would share a cap with each other.
         * Both would show up as chat mysteriously failing under perfectly normal load.
         */

        RateLimiter::for('chat-patient', function (Request $request) {
            // `throttle` runs after `auth:chat` in the route's middleware list, so the chat
            // identity is resolved by now. The IP is only a fallback for an unauthenticated
            // request, which the guard would reject anyway.
            $chatUser = $request->user('chat');

            return Limit::perMinute(120)->by(
                $chatUser ? 'chat-user:' . $chatUser->getAuthIdentifier() : 'ip:' . $request->ip()
            );
        });

        RateLimiter::for('chat-staff', function (Request $request) {
            // Server-to-server: there is no authenticated user at all, so key on the staff
            // member Medhiwa names in the body. A caller that omits it fails validation in
            // the controller regardless.
            $staffId = (int) $request->input('staff_external_id');

            return Limit::perMinute(600)->by(
                $staffId > 0 ? 'chat-staff:' . $staffId : 'ip:' . $request->ip()
            );
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
