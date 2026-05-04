<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Validator;

class ClinicalController extends Controller
{
    public function getPatientSubmitedFormData(Request $request)
    {
        try {
            $patientId = auth()->user()->patient_id;
            
            $url = "https://ptp.advantagehcs.com/api/submittedData/" . $patientId;

            $response = Http::timeout(30)
                ->acceptJson()
                ->asJson()
                ->post($url, []);

            $data = $response->json();

            foreach ($data as &$item) {

                // ❌ Remove json key
                unset($item['json']);

                if (!empty($item['pdf_url'])) {
                    $item['downloadPdf'] = "https://ptp.advantagehcs.com/storage/pdfDownload/" . $item['pdf_url'];
                } else {
                    $item['downloadPdf'] = null;
                }

                // ✅ Fix nested array (already done by you)
                if (isset($item['decoded_json'][0]) && is_array($item['decoded_json'][0])) {
                    $item['decoded_json'] = $item['decoded_json'][0];
                }

                // ✅ Remove unwanted keys from decoded_json
                if (isset($item['decoded_json']) && is_array($item['decoded_json'])) {
                    foreach ($item['decoded_json'] as &$field) {
                        unset(
                            $field['className'],
                            $field['name'],
                            $field['subtype'],
                            $field['column'],
                            $field['is_client_email'],
                            $field['inline'],
                            $field['other'],
                            $field['is_enable_chart'],
                            $field['chart_type']
                        );
                    }
                }
            }

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => $response->json()['message'] ?? 'API error',
                    'status_code' => $response->status()
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);

        } catch (\Throwable $e) {

            \Log::error('Patient API Error', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching patient form data'
            ], 500);
        }
    }

    public function downloadPatientSubmitedFormPdf(Request $request)
    {
        try {
            // ✅ Validate filenames
            $request->validate([
                'pdfUrls' => 'required|array',
                'pdfUrls.*' => 'required|string'
            ]);

            $pdfFiles = $request->pdfUrls;

            // ✅ Base URL
            $baseUrl = "https://ptp.advantagehcs.com/storage/pdfDownload/";
    
            $results = [];

            // ⚡ Parallel request (only check, no save)
            $responses = Http::pool(function ($pool) use ($pdfFiles, $baseUrl) {
                $requests = [];

                foreach ($pdfFiles as $index => $fileName) {
                    $requests[$index] = $pool->timeout(20)
                        ->retry(2, 500)
                        ->get($baseUrl . $fileName);
                }

                return $requests;
            });

            foreach ($pdfFiles as $index => $fileName) {

                    try {
                        $response = $responses[$index];

                        if (!$response) {
                            $results[] = [
                                'file' => $fileName,
                                'success' => false,
                                'message' => 'File not found'
                            ];
                            continue;
                        }

                        if ($response->status() === 404) {
                            $results[] = [
                                'file' => $fileName,
                                'success' => false,
                                'message' => 'File not found'
                            ];
                            continue;
                        }

                        if (!$response->successful()) {
                            $results[] = [
                                'file' => $fileName,
                                'success' => false,
                                'message' => 'File inaccessible'
                            ];
                            continue;
                        }

                        $contentType = $response->header('Content-Type');

                        if (!str_contains($contentType, 'application/pdf')) {
                            $results[] = [
                                'file' => $fileName,
                                'success' => false,
                                'message' => 'File not found'
                            ];
                            continue;
                        }

                        $results[] = [
                            'file' => $fileName,
                            'success' => true,
                            'url' => $baseUrl . $fileName
                        ];

                    } catch (\Exception $e) {
                        $results[] = [
                            'file' => $fileName,
                            'success' => false,
                            'message' => 'Error checking file'
                        ];
                    }
            }

            return response()->json([
                'success' => true,
                'message' => 'Pdf processing completed',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing PDF files',
            ], 500);
        }
    }

    public function viewPatientSubmitedFormPdf($formValueId)
    {
        try {

            if (!$formValueId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form value ID is required'
                ], 400);
            }

            

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching PDF data'
            ], 500);
        }
    }

    public function getPatientFormData(){
        try{
            $formSubmission = FormSubmission::with('form')
                            ->where('patient_id', auth()->user()->patient_id)
                            // ->where('status', 'active')
                            ->get();
            
            return response()->json([
                'success' => true,
                'data' => $formSubmission
            ], 200);

        }catch(\Throwable $e){
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error fetching patient form data'
            ], 500);
        }
    }
}
