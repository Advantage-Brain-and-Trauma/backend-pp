<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClinicalNoteController extends Controller
{
    /**
     * GET /api/clinical-note/{appointmentId}
     *
     * Fetches clinical note attachment data from external API by appointment ID.
     */
    public function show(Request $request): JsonResponse
    {
        try {

            $appointmentId = $request->query('appt_id');
            $appointmentId = AhcsAttendance::findorFail($appointmentId)->id;

            if(!$appointmentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid appointment ID.',
                ], 400);
            }

            $baseUrl = rtrim((string) config('services.clinical_notes.base_url'), '/');

            if ($baseUrl === '') {
                Log::channel('patient_form')->error('Clinical note base URL is not configured');

                return response()->json([
                    'success' => false,
                    'message' => 'Clinical note API is not configured.',
                ], 500);
            }

            $response = Http::timeout(30)
                ->acceptJson()
                ->get($baseUrl . '/attachments/' . $appointmentId);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => $response->json('message') ?? 'Unable to fetch clinical note.',
                    'status_code' => $response->status(),
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('patient_form')->error('Clinical note API error', [
                'appointment_id' => $appointmentId,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching clinical note.',
            ], 500);
        }
    }
}
