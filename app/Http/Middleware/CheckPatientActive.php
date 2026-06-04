<?php

namespace App\Http\Middleware;

use App\Models\AhcsCase;
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
     * Block the request if the patient linked to the active case (or any
     * linked patient when no case_id is present) has been soft-deleted in
     * ahcs_patients (deleted_at IS NOT NULL).
     *
     * On block:
     *  - All active user_sessions rows are set to is_active = 0
     *  - The current JWT is invalidated
     *  - 403 is returned
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return $next($request);
        }

        try {
            $patientIds = $user->getAllPatientIds();

            if (empty($patientIds)) {
                return $next($request);
            }

            // Resolve the patient_id to check from the active case_id in the
            // JWT token (most specific check). Fall back to all linked patients.
            $patientIdToCheck = null;

            try {
                $payload    = JWTAuth::parseToken()->getPayload();
                $caseId     = $payload->get('case_id');

                if ($caseId) {
                    $patientIdToCheck = AhcsCase::where('id', $caseId)
                        ->whereIn('patient_id', $patientIds)
                        ->value('patient_id');
                }
            } catch (\Throwable $e) {
                Log::channel('auth')->warning('CheckPatientActive: could not resolve case_id from token', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            // Build the query: check the case-specific patient if resolved,
            // otherwise check ALL linked patients.
            $query = AhcsPatient::whereNull('deleted_at');

            if ($patientIdToCheck) {
                $query->where('id', $patientIdToCheck);
            } else {
                $query->whereIn('id', $patientIds);
            }

            $activePatientExists = $query->exists();

            Log::channel('auth')->info('CheckPatientActive: patient check', [
                'user_id'            => $user->id,
                'patient_ids'        => $patientIds,
                'patient_id_checked' => $patientIdToCheck ?? 'all',
                'active'             => $activePatientExists,
            ]);

            if (!$activePatientExists) {
                // Deactivate all sessions in the DB.
                UserSession::where('user_id', $user->id)
                    ->where('is_active', 1)
                    ->update([
                        'is_active'  => 0,
                        'updated_at' => now(),
                    ]);

                Log::channel('auth')->warning('CheckPatientActive: session invalidated — patient deleted', [
                    'user_id'            => $user->id,
                    'email'              => $user->email,
                    'patient_ids'        => $patientIds,
                    'patient_id_checked' => $patientIdToCheck ?? 'all',
                ]);

                // Invalidate the current JWT.
                try {
                    Auth::guard('api')->logout();
                } catch (\Throwable) {
                    // Token already expired — safe to ignore.
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Your account is no longer active. Please contact support.',
                ], 403);
            }

        } catch (\Throwable $e) {
            // Never block a legitimate request due to an infrastructure error.
            Log::channel('auth')->error('CheckPatientActive: unexpected error', [
                'user_id' => $user->id ?? null,
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
        }

        return $next($request);
    }
}
