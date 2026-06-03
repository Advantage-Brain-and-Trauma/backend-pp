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
use Illuminate\Support\Facades\Auth;
use App\Models\UserSession;
use Tymon\JWTAuth\Token;

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
    public function getPatientDetails(): JsonResponse
    {
        try {
            Log::channel('patient')->info('Get Patient Details API hit', [
                'user_id' => auth()->id()
            ]);

            $userDetails = auth()->user();
            // Use the primary patient ID for single-patient lookups.
            $patient_id = $userDetails->getPrimaryPatientId();

            if (!$patient_id) {
                throw new \Exception("Patient ID is required", 400);
            }

            // ✅ Use findOrFail (auto throw)
            $patient = AhcsPatient::findOrFail($patient_id);
            Log::channel('patient')->info('Patient details fetched successfully', [
                'patient_id' => $patient_id,
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

            // ✅ Ensure case belongs to patient
            // $case = AhcsCase::where('patient_id', $patient_id)
            //     ->where('id', $case_id)
            //     ->first();

            // if (!$case) {
            //     throw new \Exception("Case not found for the given patient", 404);
            // }

            // $med_auth = AhcsMedAuth::where('case_id', $case_id)->first();
            // if (!$med_auth) {
            //     throw new \Exception("MedAuth not found for the given case", 404);
            // }

            // $intake = AhcsIntake::where('patient_id', $patient_id)->first();
            // if (!$intake) {
            //     throw new \Exception("Intake not found for the given patient", 404);
            // }

            // $workcamp = AhcsWorkComp::where('patient_id', $patient_id)->first();
            // if (!$workcamp) {
            //     throw new \Exception("WorkComp not found for the given patient", 404);
            // }

            Log::channel('patient')->info('Patient details returned successfully', [
                'patient_id' => $patient_id,
            ]);

            return response()->json([
                'success' => true,
                'patient_details' => $patientDetails,
                // 'case_details' => $case->toArray(),
                // 'med_auth_details' => $med_auth->toArray(),
                // 'intake_details' => $intake->toArray(),
                // 'workcamp_details' => $workcamp->toArray(),
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
            $patient_ids = $userDetails->getAllPatientIds();

            if (empty($patient_ids)) {
                throw new \Exception("Patient ID is required", 400);
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
    //             throw new \Exception('User not found', 404);
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

            $email = $authUser->email;

            Log::channel('patient')->info('Get case IDs API hit', [
                'user_id' => $authUser->id,
                'email'   => $email,
            ]);

            $patientIds = AhcsPatient::where('email', $email)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->unique()
                ->values()
                ->toArray();

            if (empty($patientIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No patients found for this email',
                    'case_ids' => [],
                ], 404);
            }

            $caseIds = AhcsCase::whereIn('patient_id', $patientIds)
                ->whereNull('deleted_at')
                ->pluck('id') // change to 'case_id' if you want actual case_id
                ->unique()
                ->values()
                ->toArray();

            Log::channel('patient')->info('Case IDs fetched successfully', [
                'email'       => $email,
                'patient_ids' => $patientIds,
                'case_count'  => count($caseIds),
            ]);

            return response()->json([
                'success'     => true,
                'email'       => $email,
                'patient_ids' => $patientIds,
                'case_ids'    => $caseIds,
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
