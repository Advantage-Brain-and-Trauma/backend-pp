<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyInternalApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $expectedKey = config('services.internal_api.key');

        if (!$expectedKey || $request->header('X-API-KEY') !== $expectedKey) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing API key',
            ], 401);
        }

        return $next($request);
    }
}
