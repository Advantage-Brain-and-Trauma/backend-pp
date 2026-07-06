<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AhcsPatient;
use App\Models\AhcsCase;
use App\Models\AhcsIntake;
use App\Models\AhcsMedAuth;
use App\Models\AhcsWorkComp;
use App\Models\PatientCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Token;
use Illuminate\Support\Facades\Auth;
use App\Models\ProxyAccess;
use App\Models\UserSession;
use Exception;

class PatientController extends Controller
{
    /**
     * GET /api/get-patient-details
     *
     * Returns the authenticated patient's basic profile details.
     *
     * Request Payload:
     * - None
     *
     * Response:
     * - 200: { success: true, patient_details: { id, first_name, last_name, full_name, dob, email, home_phone, address1 } }
     * - 404: { success: false, message: string }
     * - 500: { success: false, message: string }
     */
    public function getPatientDetails(Request $request): JsonResponse
    {
        try {
            Log::channel('patient')->info('Get Patient Details API hit', [
                'user_id' => auth()->id()
            ]);

            $caseId      = $request->query('case_id');
            $userDetails = auth()->user();
            $patientIds  = $userDetails->getActivePatientIds();

            if (empty($caseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case ID is required.',
                ], 422);
            }

            $caseRecord = AhcsCase::where('id', $caseId)
                ->whereIn('patient_id', $patientIds)
                ->first(['patient_id']);

            if (!$caseRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Case ID for this patient.',
                ], 422);
            }

            $patient_id = $caseRecord->patient_id;

            // ✅ Use findOrFail (auto throw)
            $patient = AhcsPatient::findOrFail($patient_id);
            Log::channel('patient')->info('Patient details fetched successfully', [
                'patient_id' => $patient_id,
                'case_id'    => $caseId,
            ]);

            $patientDetails = [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'full_name' => $patient->patient_name,
                'dob' => $patient->dob,
                'email' => $patient->email,
                'home_phone' => $patient->cell_no ?? $patient->home_ph,
                'address1' => $patient->address1,

            ];

            Log::channel('patient')->info('Patient details returned successfully', [
                'patient_id' => $patient_id,
            ]);

            return response()->json([
                'success'         => true,
                'patient_details' => $patientDetails,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found'
            ], 404);

        } catch (\Throwable $e) {
            Log::channel('patient')->error("Error fetching patient details: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ],500);
        }
    }

    /**
     * GET /api/get-patient-info?case_id={caseId}
     *
     * Proxies patient info from the app server for the authenticated patient's case.
     * patient_id is resolved server-side from the case record (not trusted from the client).
     *
     * Request Payload:
     * - Query: case_id (required)
     *
     * Response:
     * - 200: proxied response body from the app server
     * - 422: { success: false, message: string }
     * - 502: { status: false, message: string, error: string }
     */
    public function getPatientInfo(Request $request): JsonResponse
    {
        try {
            Log::channel('patient')->info('Get Patient Info API hit', [
                'user_id' => auth()->id()
            ]);

            $caseId = $request->query('case_id');

            if (empty($caseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case ID is required.',
                ], 422);
            }

            $patientIds = auth()->user()->getActivePatientIds();

            $caseRecord = AhcsCase::where('id', $caseId)
                ->whereIn('patient_id', $patientIds)
                ->first(['patient_id']);

            if (!$caseRecord) {
                Log::channel('patient')->warning('Invalid case ID for user', [
                    'user_id' => auth()->id(),
                    'case_id' => $caseId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Case ID for this patient.',
                ], 422);
            }

            $patientId = $caseRecord->patient_id;

            $params = [
                'patient_id' => $patientId,
                'case_id'    => $caseId,
            ];

            $url = config('services.app_server.staging_url') . '/patient-portal/get-patient-info?' . http_build_query($params);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            ]);

            $body     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                Log::channel('patient')->error('getPatientInfo curl error: ' . $curlErr);
                return response()->json([
                    'status'  => false,
                    'message' => 'Failed to reach patient info service.',
                    'error'   => $curlErr,
                ], 502);
            }

            $data = json_decode($body, true);

            Log::channel('patient')->info('Patient info fetched successfully', [
                'patient_id' => $patientId,
                'case_id'    => $caseId,
                'http_code'  => $httpCode,
            ]);

            return response()->json($data ?? [], $httpCode ?: 500);

        } catch (\Throwable $e) {
            Log::channel('patient')->error('Error fetching patient info: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    /**
     * GET /api/get-case-ids-by-patient-id
     *
     * Returns all case IDs for the authenticated patient.
     *
     * Request Payload:
     * - None
     *
     * Response:
     * - 200: { success: true, case_ids: int[] }
     * - 500: { success: false, message: string }
     */
    public function getCaseIdsByPatientId(): JsonResponse
    {
        try {
            Log::channel('patient')->info('Get case IDs API hit', [
                'user_id' => auth()->id(),
            ]);

            $userDetails = auth()->user();
            // Collect ALL patient IDs so cases for every linked patient are returned.
            $patient_ids = $userDetails->getActivePatientIds();

            if (empty($patient_ids)) {
                throw new Exception("Patient ID is required", 400);
            }

            $caseIds = AhcsCase::whereIn('patient_id', $patient_ids)
                ->pluck('id')
                ->toArray();

            Log::channel('patient')->info('Case IDs fetched successfully', [
                'patient_ids' => $patient_ids,
                'case_count'  => count($caseIds),
            ]);

            return response()->json([
                'success' => true,
                'case_ids' => $caseIds
            ], 200);

        } catch (\Throwable $e) {
            Log::channel('patient')->error("Error fetching case IDs: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ],500);
        }
    }

    /**
     * GET /api/get-case-ids-by-email
     *
     * Returns all case IDs for the authenticated patient.
     *
     * Request Payload:
     * - None
     *
     * Response:
     * - 200: { success: true, case_ids: int[] }
     * - 500: { success: false, message: string }
     */
    // public function getCaseIdsByEmail(): JsonResponse
    // {
    //     try {
    //         Log::channel('patient')->info('Get case IDs API hit', [
    //             'user_id' => auth()->id(),
    //             'email'   => auth()->user()->email,
    //         ]);

    //         $email = auth()->user()->email;

    //         $user = User::where('email', $email)->first();

    //         if (!$user) {
    //             throw new Exception('User not found', 404);
    //         }

    //         $patientIds = [];
    //         $patientIds = AhcsPatient::where('email', $email)
    //             ->pluck('id')
    //             ->toArray();

    //         $caseIds = AhcsCase::whereIn('patient_id', $patientIds)
    //             ->pluck('id')
    //             ->toArray();

    //         Log::channel('patient')->info('Case IDs fetched successfully', [
    //             'email'      => $email,
    //             'patient_id' => $user->patient_id,
    //             'case_count' => count($caseIds),
    //         ]);

    //         return response()->json([
    //             'success'  => true,
    //             'case_ids' => $caseIds,
    //         ], 200);

    //     } catch (\Throwable $e) {
    //         Log::channel('patient')->error('Error fetching case IDs', [
    //             'message' => $e->getMessage(),
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    // public function getCaseIdsByEmail(): JsonResponse
    // {
    //     try {
    //         $authUser = auth()->user();

    //         if (!$authUser) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Unauthenticated user',
    //             ], 401);
    //         }

    //         Log::channel('patient')->info('Get case IDs by email API hit', [
    //             'user_id' => $authUser->id,
    //             'email'   => $authUser->email,
    //         ]);

    //         // ── Proxy context: read from JWT claims ───────────────────────
    //         // When a proxy calls POST /api/proxy/switch-patient, a new JWT is
    //         // issued with proxy_context embedded (patient_ids + case_ids).
    //         // Read directly from the token — no session required.
    //         $payload      = JWTAuth::parseToken()->getPayload();
    //         $proxyContext = $payload->get('proxy_context');

    //         if (!empty($proxyContext)) {
    //             $patientIds = array_values(array_map('intval', $proxyContext['patient_ids'] ?? []));
    //             $caseIds    = array_values(array_map('intval', $proxyContext['case_ids']    ?? []));

    //             Log::channel('patient')->info('Proxy JWT context — returning patient data', [
    //                 'proxy_user_id'   => $authUser->id,
    //                 'patient_user_id' => $proxyContext['patient_user_id'] ?? null,
    //                 'patient_ids'     => $patientIds,
    //                 'case_count'      => count($caseIds),
    //             ]);

    //             return response()->json([
    //                 'success'     => true,
    //                 'email'       => $authUser->email,
    //                 'patient_ids' => $patientIds,
    //                 'case_ids'    => $caseIds,
    //             ], 200);
    //         }

    //         // ── Regular patient (no proxy context in token) ───────────────
    //         $userPatientIds = array_values(array_map('intval', $authUser->getActivePatientIds()));

    //         if ($authUser->is_proxy_account) {
    //             // Proxy account: fetch patients by patient_id only — no email filter,
    //             // because the proxy user's email differs from the patient's email.
    //             Log::channel('patient')->info('Proxy account — fetching patients by patient_id only', [
    //                 'proxy_user_id'   => $authUser->id,
    //                 'user_patient_ids' => $userPatientIds,
    //             ]);

    //             $patientIds = AhcsPatient::whereIn('id', $userPatientIds)
    //                 ->whereNull('deleted_at')
    //                 ->pluck('id')
    //                 ->map(fn ($id) => (int) $id)
    //                 ->unique()
    //                 ->values()
    //                 ->toArray();
    //         } else {
    //             // Regular patient: fetch patients by patient_id AND email.
    //             Log::channel('patient')->info('Regular patient — fetching patients by patient_id and email', [
    //                 'user_id'          => $authUser->id,
    //                 'email'            => $authUser->email,
    //                 'user_patient_ids' => $userPatientIds,
    //             ]);

    //             $patientIds = AhcsPatient::whereIn('id', $userPatientIds)
    //                 ->where('email', $authUser->email)
    //                 ->whereNull('deleted_at')
    //                 ->pluck('id')
    //                 ->map(fn ($id) => (int) $id)
    //                 ->unique()
    //                 ->values()
    //                 ->toArray();
    //         }

    //         if (empty($patientIds)) {
    //             Log::channel('patient')->warning('No patients found', [
    //                 'user_id'          => $authUser->id,
    //                 'is_proxy_account' => (bool) $authUser->is_proxy_account,
    //                 'user_patient_ids' => $userPatientIds,
    //             ]);
    //             return response()->json([
    //                 'success'  => false,
    //                 'message'  => 'No patients found for this email',
    //                 'case_ids' => [],
    //             ], 404);
    //         }

    //         $caseIds = AhcsCase::whereIn('patient_id', $patientIds)
    //             ->whereNull('deleted_at')
    //             ->pluck('id')
    //             ->unique()
    //             ->values()
    //             ->toArray();

    //         Log::channel('patient')->info('Case IDs fetched successfully', [
    //             'user_id'          => $authUser->id,
    //             'is_proxy_account' => (bool) $authUser->is_proxy_account,
    //             'patient_ids'      => $patientIds,
    //             'case_count'       => count($caseIds),
    //         ]);

    //         return response()->json([
    //             'success'     => true,
    //             'email'       => $authUser->email,
    //             'patient_ids' => $patientIds,
    //             'case_ids'    => $caseIds,
    //         ], 200);

    //     } catch (\Throwable $e) {
    //         Log::channel('patient')->error('Error fetching case IDs', [
    //             'message' => $e->getMessage(),
    //             'line'    => $e->getLine(),
    //             'file'    => $e->getFile(),
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Something went wrong while fetching case IDs',
    //         ], 500);
    //     }
    // }

    public function getCaseIdsByEmail(): JsonResponse
    {
        try {
            $authUser = auth()->user();

            if (!$authUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated user',
                ], 401);
            }

            Log::channel('patient')->info('Get case IDs by email API hit', [
                'user_id' => $authUser->id,
                'email'   => $authUser->email,
            ]);

            // ── Proxy context: read from JWT claims ───────────────────────
            // When a proxy calls POST /api/proxy/switch-patient, a new JWT is
            // issued with proxy_context embedded (patient_ids + case_ids).
            // Read directly from the token — no session required.
            $payload      = JWTAuth::parseToken()->getPayload();
            $proxyContext = $payload->get('proxy_context');

            if (!empty($proxyContext)) {
                $patientIds = array_values(array_map('intval', $proxyContext['patient_ids'] ?? []));
                $caseIds    = array_values(array_map('intval', $proxyContext['case_ids']    ?? []));

                Log::channel('patient')->info('Proxy JWT context — returning patient data', [
                    'proxy_user_id'   => $authUser->id,
                    'patient_user_id' => $proxyContext['patient_user_id'] ?? null,
                    'patient_ids'     => $patientIds,
                    'case_count'      => count($caseIds),
                ]);

                $cases = AhcsCase::whereIn('id', $caseIds)
                    ->whereIn('patient_id', $patientIds)
                    ->get(['id', 'patient_id', 'doi'])
                    ->map(function ($case) {
                        $patient = AhcsPatient::where('id', $case->patient_id)->first(['patient_name']);
                        return ($patient->patient_name ?? '') . ' - ' . ($case->doi ?? '') . ' - ' . $case->id;
                    })
                    ->values();

                return response()->json([
                    'success'     => true,
                    'email'       => $authUser->email,
                    'patient_ids' => $patientIds,
                    'case_ids'    => $caseIds,
                    'cases'       => $cases,
                ], 200);
            }

            // ── Regular patient (no proxy context in token) ───────────────
            $userPatientIds = array_values(array_map('intval', $authUser->getActivePatientIds()));

            if ($authUser->is_proxy_account) {
                // Proxy account: fetch patients by patient_id only — no email filter,
                // because the proxy user's email differs from the patient's email.
                Log::channel('patient')->info('Proxy account — fetching patients by patient_id only', [
                    'proxy_user_id'   => $authUser->id,
                    'user_patient_ids' => $userPatientIds,
                ]);

                $patientIds = AhcsPatient::whereIn('id', $userPatientIds)
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->toArray();
            } else {
                // Regular patient: fetch patients by patient_id AND email.
                Log::channel('patient')->info('Regular patient — fetching patients by patient_id and email', [
                    'user_id'          => $authUser->id,
                    'email'            => $authUser->email,
                    'user_patient_ids' => $userPatientIds,
                ]);

                $patientIds = AhcsPatient::whereIn('id', $userPatientIds)
                    ->where('email', $authUser->email)
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->toArray();
            }

            if (empty($patientIds)) {
                Log::channel('patient')->warning('No patients found', [
                    'user_id'          => $authUser->id,
                    'is_proxy_account' => (bool) $authUser->is_proxy_account,
                    'user_patient_ids' => $userPatientIds,
                ]);
                return response()->json([
                    'success'  => false,
                    'message'  => 'No patients found for this email',
                    'case_ids' => [],
                ], 404);
            }

            $caseRecords = AhcsCase::whereIn('patient_id', $patientIds)
                ->whereNull('deleted_at')
                ->get(['id', 'patient_id', 'doi'])
                ->unique('id')
                ->values();

            $caseIds = $caseRecords->pluck('id')->values()->toArray();

            $patientNamesById = AhcsPatient::whereIn('id', $patientIds)
                ->pluck('patient_name', 'id');

            $cases = $caseRecords->map(function ($case) use ($patientNamesById) {
                $patientName = $patientNamesById[$case->patient_id] ?? '';
                return $patientName . ' - ' . ($case->doi ?? '') . ' - ' . $case->id;
            })->values();

            Log::channel('patient')->info('Case IDs fetched successfully', [
                'user_id'          => $authUser->id,
                'is_proxy_account' => (bool) $authUser->is_proxy_account,
                'patient_ids'      => $patientIds,
                'case_count'       => count($caseIds),
            ]);

            return response()->json([
                'success'     => true,
                'email'       => $authUser->email,
                'patient_ids' => $patientIds,
                'case_ids'    => $caseIds,
                'cases'       => $cases,
            ], 200);

        } catch (\Throwable $e) {
            Log::channel('patient')->error('Error fetching case IDs', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching case IDs',
            ], 500);
        }
    }

    /**
     * GET /api/get-user-details-by-email
     *
     * Public lookup: returns basic user/patient details for a given email.
     * Password is never included in the response.
     *
     * Request Payload:
     * - email (required, string)
     *
     * Response:
     * - 200: { success: true, data: { email, name, phone, patient_id } }
     * - 404: { success: false, message: string }
     * - 422: { success: false, message: string }
     */
    public function getUserDetailsByEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $email = $request->query('email');

        Log::channel('patient')->info('Get user details by email API hit', [
            'email' => $email,
        ]);

        $user = User::where('email', $email)->first(['email','password', 'name', 'phone', 'patient_id']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No user found for this email',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'email'      => $user->email,
                'password'  => str_random(10), // Return a random string instead of the actual password
                'name'       => $user->name,
                'phone'      => $user->phone,
                'patient_id' => $user->patient_id ?? [],
            ],
        ], 200);
    }

    /**
     * POST /api/change-patient-case
     *
     * Switches the active patient case in JWT claims and updates the active user session token.
     *
     * Request Payload:
     * - case_id (required, integer)
     *
     * Response:
     * - 200: { success: true, message: string, token: string }
     * - 401: { success: false, message: string }
     * - 404: { success: false, message: string }
     * - 422: { success: false, message: string }
     */
    public function changePatientCase(Request $request): JsonResponse
    {
        try {
            Log::channel('auth')->info('Change patient case API hit', [
                'user_id' => auth()->id(),
                'case_id' => $request->case_id,
            ]);

            $validator = Validator::make($request->all(), [
                'case_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                Log::channel('auth')->warning('Change patient case validation failed', [
                    'user_id' => auth()->id(),
                    'error' => $validator->errors()->first(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $oldToken = JWTAuth::parseToken()->getToken();

            if (!$oldToken) {
                Log::channel('auth')->warning('Change patient case failed: token missing', [
                    'user_id' => auth()->id(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Token not provided'
                ], 401);
            }

            $oldPayload = JWTAuth::parseToken()->getPayload();
            $oldJwtId = $oldPayload->get('jti');

            $user = Auth::guard('api')->user();

            if (!$user) {
                Log::channel('auth')->warning('Change patient case failed: unauthenticated user');

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated user'
                ], 401);
            }

            $caseId = $request->case_id;
            
            $checkCaseId = AhcsCase::where('patient_id', $user->getPrimaryPatientId())
                ->where('id', $caseId)
                ->exists();

            if (!$checkCaseId) {
                Log::channel('auth')->warning('Change patient case failed: invalid case', [
                    'user_id' => $user->id,
                    'patient_id' => $user->getPrimaryPatientId(),
                    'case_id' => $caseId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Case Id'
                ], 404);
            }

            $newToken = JWTAuth::claims([
                'case_id' => $caseId
            ])->fromUser($user);

            $newPayload = JWTAuth::manager()->decode(new Token($newToken));
            $newJwtId = $newPayload->get('jti');

            UserSession::where('user_id', $user->id)
                ->where('jwt_id', $oldJwtId)
                ->where('is_active', 1)
                ->update([
                    'jwt_id' => $newJwtId,
                    'token' => $newToken,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'last_activity' => now(),
                    'updated_at' => now(),
                ]);

            Log::channel('auth')->info('Patient case changed successfully', [
                'user_id' => $user->id,
                'case_id' => $caseId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Patient case changed successfully',
                'token' => $newToken,
            ], 200);

        } catch (\Throwable $e) {
            Log::channel('auth')->error('Change patient case failed', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Patient case change failed'
            ], 401);
        }
    }

}
