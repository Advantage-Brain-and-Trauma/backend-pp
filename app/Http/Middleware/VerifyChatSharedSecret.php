<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server authentication for the chat subsystem.
 *
 * Guards POST /api/chat/identify/external and the whole /api/chat/staff/* group. The caller's
 * own backend (Medhiwa) vouches for the staff identity it names, so possession of this secret
 * is equivalent to being able to act as ANY staff member — hence the IP allowlist and the
 * timing-safe comparison.
 *
 * The secret must never reach a browser: Medhiwa proxies these calls from its own server and
 * the staff page talks only to Medhiwa.
 */
class VerifyChatSharedSecret
{
    public function handle(Request $request, Closure $next)
    {
        $expectedSecret = config('chat.shared_secret');
        $presented = (string) $request->header('X-CHAT-SECRET', '');

        // hash_equals instead of !== so a wrong secret cannot be discovered a byte at a time
        // by timing the response. Both arguments must be strings.
        if (!is_string($expectedSecret) || $expectedSecret === '' || !hash_equals($expectedSecret, $presented)) {
            $this->deny($request, 'secret');

            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing chat secret',
            ], 401);
        }

        $allowlist = (array) config('chat.staff_ip_allowlist', []);

        // An EMPTY allowlist means no IP restriction — the behaviour this middleware had
        // before the allowlist existed. That keeps deploying this change from locking out an
        // environment whose .env has not been updated yet; production should populate it.
        if ($allowlist !== [] && !in_array((string) $request->ip(), $allowlist, true)) {
            $this->deny($request, 'ip');

            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing chat secret',
            ], 401);
        }

        return $next($request);
    }

    /**
     * Records the refusal without ever writing the presented secret, the request body, or any
     * patient data to the log.
     */
    private function deny(Request $request, string $reason): void
    {
        Log::channel('chat')->warning('Chat shared-secret request refused', [
            'reason' => $reason,
            'ip'     => $request->ip(),
            'path'   => $request->path(),
        ]);
    }
}
