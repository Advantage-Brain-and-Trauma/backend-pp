<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    public function getPatientDetails(): JsonResponse
    {
        try {
            Log::channel('patient')->info('Get Patient Details API hit', [
                'user_id' => auth()->id()
            ]);

            $userDetails = auth()->user();
            $patient_id = $userDetails->patient_id;
            // $case_id = $userDetails->case_id ?? 10004802;

            if (!$patient_id) {
                throw new \Exception("Patient ID is required", 400);
            }

            // if (!$case_id) {
            //     throw new \Exception("Case ID is required", 400);
            // }

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
                'home_phone' => $patient->home_ph,
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

    public function getCaseIdsByPatientId(): JsonResponse
    {
        try {
            $userDetails = auth()->user();
            $patient_id = $userDetails->patient_id;

            if (!$patient_id) {
                throw new \Exception("Patient ID is required", 400);
            }

            // $caseIds = PatientCase::where('patient_id', $patient_id)
            //     ->pluck('case_id')
            //     ->toArray();

            $caseIds = AhcsCase::where('patient_id', $patient_id)
                ->pluck('id')
                ->toArray();

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

    // public function changePatientCase(Request $request): JsonResponse
    // {
    //     try {

    //         $validator = Validator::make($request->all(), [
    //             'case_id' => 'required|integer|exists:patient_cases,case_id',
    //         ]);
    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => $validator->errors()->first()
    //             ], 422);
    //         }

    //         $oldToken = JWTAuth::getToken();

    //         if (!$oldToken) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Token not provided'
    //             ], 401);
    //         }

    //         $oldPayload = JWTAuth::setToken($oldToken)->getPayload();
    //         $oldJwtId = $oldPayload->get('jti');

    //         $newToken = Auth::guard('api')->refresh();

    //         $newPayload = JWTAuth::setToken($newToken)->getPayload();
    //         $newJwtId = $newPayload->get('jti');

    //         $user = Auth::guard('api')->setToken($newToken)->user();

    //         UserSession::where('user_id', $user->id)
    //             ->where('jwt_id', $oldJwtId)
    //             ->where('is_active', 1)
    //             ->update([
    //                 'jwt_id' => $newJwtId,
    //                 'token' => $newToken,
    //                 'ip_address' => $request->ip(),
    //                 'user_agent' => $request->userAgent(),
    //                 'last_activity' => now(),
    //                 'updated_at' => now(),
    //             ]);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Token refreshed successfully',
    //             'token' => $newToken,
    //         ], 200);

    //     } catch (\Throwable $e) {
    //         Log::channel('auth')->error('Token refresh failed', [
    //             'message' => $e->getMessage()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Token refresh failed'
    //         ], 401);
    //     }
    // }

    public function changePatientCase(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'case_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $oldToken = JWTAuth::parseToken()->getToken();

            if (!$oldToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token not provided'
                ], 401);
            }

            $oldPayload = JWTAuth::parseToken()->getPayload();
            $oldJwtId = $oldPayload->get('jti');

            $user = Auth::guard('api')->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated user'
                ], 401);
            }

            $caseId = $request->case_id;
            
            $checkCaseId = AhcsCase::where('patient_id', $user->patient_id)
                ->where('id', $caseId)
                ->exists();

            if (!$checkCaseId) {
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
