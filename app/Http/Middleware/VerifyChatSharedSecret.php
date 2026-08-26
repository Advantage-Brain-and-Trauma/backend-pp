<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyChatSharedSecret
{
    public function handle(Request $request, Closure $next)
    {
        $expectedSecret = config('chat.shared_secret');

        if (!$expectedSecret || $request->header('X-CHAT-SECRET') !== $expectedSecret) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing chat secret',
            ], 401);
        }

        return $next($request);
    }
}
