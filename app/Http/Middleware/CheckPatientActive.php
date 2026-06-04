<?php

namespace App\Http\Middleware;

use App\Models\AhcsPatient;
use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckPatientActive
{
    /**
     * Reject the request and invalidate the session if all of the
     * authenticated user's linked patients have been soft-deleted
     * (deleted_at IS NOT NULL) in the ahcs_patients table.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return $next($request);
        }

        $patientIds = $user->getAllPatientIds();

        if (empty($patientIds)) {
            return $next($request);
        }

        $activePatientExists = AhcsPatient::whereIn('id', $patientIds)
            ->whereNull('deleted_at')
            ->exists();

        if (!$activePatientExists) {
            // Deactivate all active sessions for this user in the DB.
            UserSession::where('user_id', $user->id)
                ->where('is_active', 1)
                ->update([
                    'is_active'  => 0,
                    'updated_at' => now(),
                ]);

            Log::channel('auth')->warning('Session invalidated: all linked patients are deleted', [
                'user_id'     => $user->id,
                'email'       => $user->email,
                'patient_ids' => $patientIds,
            ]);

            // Invalidate the current JWT token.
            try {
                Auth::guard('api')->logout();
            } catch (\Throwable) {
                // Ignore if token is already expired.
            }

            return response()->json([
                'success' => false,
                'message' => 'Your account is no longer active. Please contact support.',
            ], 403);
        }

        return $next($request);
    }
}
