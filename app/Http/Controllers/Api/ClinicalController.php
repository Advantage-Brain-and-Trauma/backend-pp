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

    // public function downloadPdf(Request $request)
    // {
    //     try {
    //         // ✅ Validate input
    //         $validator = Validator::make($request->all(), [
    //             'pdfUrl' => 'required|url'
    //         ]);
            
    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Invalid URL provided',
    //                 'errors' => $validator->errors()
    //             ], 422);
    //         }

    //         $pdfUrl = $request->pdfUrl;

    //         // ✅ Get filename from URL
    //         $filename = basename(parse_url($pdfUrl, PHP_URL_PATH));

    //         // ✅ Storage path (recommended Laravel way)
    //         $saveDir = public_path('assets/images/activecollab/pdf');

    //         if (!File::exists($saveDir)) {
    //             File::makeDirectory($saveDir, 0777, true, true);
    //         }

    //         $savePath = $saveDir . '/' . $filename;

    //         // ✅ Download PDF using Laravel HTTP client
    //         $response = Http::get($pdfUrl);

    //         if (!$response->successful()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'error' => 'Failed to download pdf'
    //             ], 500);
    //         }

    //         // ✅ Save file
    //         File::put($savePath, $response->body());

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'PDF downloaded',
    //             'savedUrl' => asset('assets/images/activecollab/pdf/' . $filename)
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // public function downloadPatientSubmitedFormPdf(Request $request)
    // {
    //     try {
    //         // ✅ Validate input (array of URLs)
    //         $validator = Validator::make($request->all(), [
    //             'pdfUrls' => 'required|array',
    //             'pdfUrls.*' => 'required|url'
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Invalid URLs provided',
    //                 'errors' => $validator->errors()
    //             ], 422);
    //         }

    //         $pdfUrls = $request->pdfUrls;

    //         $saveDir = public_path('assets/images/activecollab/pdf');

    //         if (!File::exists($saveDir)) {
    //             File::makeDirectory($saveDir, 0777, true, true);
    //         }

    //         $results = [];

    //         foreach ($pdfUrls as $pdfUrl) {

    //             try {
    //                 // ✅ Get filename
    //                 $filename = basename(parse_url($pdfUrl, PHP_URL_PATH));

    //                 // Optional: avoid duplicate names
    //                 $filename = time() . '_' . $filename;

    //                 $savePath = $saveDir . '/' . $filename;

    //                 // ✅ Download
    //                 $response = Http::get($pdfUrl);

    //                 if (!$response->successful()) {
    //                     $results[] = [
    //                         'url' => $pdfUrl,
    //                         'success' => false,
    //                         'error' => 'Download failed'
    //                     ];
    //                     continue;
    //                 }

    //                 // ✅ Save file
    //                 File::put($savePath, $response->body());

    //                 $results[] = [
    //                     'url' => $pdfUrl,
    //                     'success' => true,
    //                     'savedUrl' => asset('assets/images/activecollab/pdf/' . $filename)
    //                 ];

    //             } catch (\Exception $e) {
    //                 $results[] = [
    //                     'url' => $pdfUrl,
    //                     'success' => false,
    //                     'error' => $e->getMessage()
    //                 ];
    //             }
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'PDF processing completed',
    //             'data' => $results
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function downloadPatientSubmitedFormPdf(Request $request)
    {
        try {
            // ✅ Validate filenames
            $validator = Validator::make($request->all(), [
                'pdfUrls' => 'required|array',
                'pdfUrls.*' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid filenames provided',
                    'errors' => $validator->errors()
                ], 422);
            }

            $pdfFiles = $request->pdfUrls;

            // ✅ Source base URL (where files currently exist)
            $baseUrl = "http://10.0.0.24/medhiwa/internal/assets/images/activecollab/pdf/";

            // ✅ Upload API URL
            $uploadApi = "http://10.0.0.24/api/upload-pdf";

            $results = [];

            foreach ($pdfFiles as $fileName) {

                try {
                    // 🔹 Step 1: Build full source URL
                    $sourceUrl = $baseUrl . $fileName;

                    // 🔹 Step 2: Download file
                    $response = Http::timeout(20)
                        ->retry(2, 500)
                        ->get($sourceUrl);

                    if (!$response->successful()) {
                        $results[] = [
                            'file' => $fileName,
                            'success' => false,
                            'error' => 'Download failed'
                        ];
                        continue;
                    }

                    // 🔹 Step 3: Upload file to 10.0.0.24
                    $uploadResponse = Http::timeout(20)
                        ->attach('file', $response->body(), $fileName)
                        ->post($uploadApi);

                    if (!$uploadResponse->successful()) {
                        $results[] = [
                            'file' => $fileName,
                            'success' => false,
                            'error' => 'Upload failed'
                        ];
                        continue;
                    }

                    $uploadData = $uploadResponse->json();

                    $results[] = [
                        'file' => $fileName,
                        'success' => true,
                        'uploadedUrl' => $uploadData['url'] ?? null
                    ];

                } catch (\Exception $e) {
                    $results[] = [
                        'file' => $fileName,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Process completed',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
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
