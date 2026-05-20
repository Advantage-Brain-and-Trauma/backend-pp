<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AhcsPatient;
use App\Models\AhcsCase;
use App\Models\AhcsIntake;
use App\Models\AhcsMedAuth;
use App\Models\AhcsWorkComp;
use App\Modles\PatientCases;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

            $caseIds = PatientCases::where('patient_id', $patient_id)
                ->pluck('case_id')
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
}
