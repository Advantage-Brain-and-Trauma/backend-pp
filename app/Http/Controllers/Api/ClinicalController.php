<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use App\Models\FormSubmission;
use App\Models\FormSubmissionNote;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Illuminate\Support\Facades\Log;

class ClinicalController extends Controller
{
    public function getPatientSubmitedFormData(Request $request)
    {
        try {

            $patientId = auth()->user()->patient_id;

            Log::channel('patient_form')->info('Fetching submitted patient form data started', [
                'user_id'    => auth()->id(),
                'patient_id' => $patientId
            ]);

            $url = "https://ptp.advantagehcs.com/api/submittedData/" . $patientId;

            Log::channel('patient_form')->info('Calling external submitted form API', [
                'url' => $url
            ]);

            $response = Http::timeout(30)
                ->acceptJson()
                ->asJson()
                ->post($url, []);

            Log::channel('patient_form')->info('External API response received', [
                'status_code' => $response->status()
            ]);

            $data = $response->json();

            foreach ($data as &$item) {

                unset($item['json']);

                if (!empty($item['pdf_url'])) {

                    $item['downloadPdf'] = "https://ptp.advantagehcs.com/storage/pdfDownload/" . $item['pdf_url'];

                } else {

                    $item['downloadPdf'] = null;
                }

                if (isset($item['decoded_json'][0]) && is_array($item['decoded_json'][0])) {
                    $item['decoded_json'] = $item['decoded_json'][0];
                }

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

            Log::channel('patient_form')->info('Submitted patient form data processed', [
                'patient_id' => $patientId,
                'total_records' => is_array($data) ? count($data) : 0
            ]);

            if ($response->failed()) {

                Log::channel('patient_form')->warning('External API returned failure response', [
                    'patient_id' => $patientId,
                    'status_code' => $response->status()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $response->json()['message'] ?? 'API error',
                    'status_code' => $response->status()
                ], $response->status());
            }

            Log::channel('patient_form')->info('Submitted patient form data fetched successfully', [
                'patient_id' => $patientId
            ]);

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);

        } catch (\Throwable $e) {

            Log::channel('patient_form')->error('Patient submitted form API error', [
                'patient_id' => $patientId ?? null,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
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

            Log::channel('patient_form')->info('Download submitted form PDFs started', [
                'user_id' => auth()->id(),
                'pdf_count' => count($request->pdfUrls ?? [])
            ]);

            $request->validate([
                'pdfUrls' => 'required|array',
                'pdfUrls.*' => 'required|string'
            ]);

            $pdfFiles = $request->pdfUrls;
            $baseUrl = "https://ptp.advantagehcs.com/storage/pdfDownload/";

            $tempDir = storage_path('app/temp_pdfs');

            if (!file_exists($tempDir)) {

                mkdir($tempDir, 0777, true);

                Log::channel('patient_form')->info('Temporary PDF directory created', [
                    'temp_dir' => $tempDir
                ]);
            }

            $zipFileName = 'patient_pdfs_' . time() . '.zip';
            $zipPath = storage_path("app/" . $zipFileName);

            Log::channel('patient_form')->info('ZIP creation initiated', [
                'zip_path' => $zipPath
            ]);

            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {

                Log::channel('patient_form')->error('Could not create ZIP file', [
                    'zip_path' => $zipPath
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Could not create ZIP file'
                ], 500);
            }

            $responses = Http::pool(function ($pool) use ($pdfFiles, $baseUrl) {

                $requests = [];

                foreach ($pdfFiles as $index => $fileName) {

                    Log::channel('patient_form')->info('Fetching remote PDF', [
                        'file_name' => $fileName
                    ]);

                    $requests[$index] = $pool->timeout(20)
                        ->retry(2, 500)
                        ->get($baseUrl . $fileName);
                }

                return $requests;
            });

            foreach ($pdfFiles as $index => $fileName) {

                try {

                    $response = $responses[$index];

                    if (!$response || !$response->successful()) {

                        Log::channel('patient_form')->warning('PDF fetch failed', [
                            'file_name' => $fileName
                        ]);

                        continue;
                    }

                    if (!str_contains($response->header('Content-Type'), 'application/pdf')) {

                        Log::channel('patient_form')->warning('Invalid PDF content type', [
                            'file_name' => $fileName,
                            'content_type' => $response->header('Content-Type')
                        ]);

                        continue;
                    }

                    // Save file temporarily
                    $filePath = $tempDir . '/' . $fileName;

                    file_put_contents($filePath, $response->body());

                    // Add to ZIP
                    $zip->addFile($filePath, $fileName);

                    Log::channel('patient_form')->info('PDF added to ZIP', [
                        'file_name' => $fileName
                    ]);

                } catch (\Exception $e) {

                    Log::channel('patient_form')->error('Error processing PDF file', [
                        'file_name' => $fileName,
                        'error' => $e->getMessage()
                    ]);

                    continue;
                }
            }

            $zip->close();

            Log::channel('patient_form')->info('ZIP file created successfully', [
                'zip_path' => $zipPath
            ]);

            // Clean temp files after response
            return response()->download($zipPath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {

            Log::channel('patient_form')->error('Error creating ZIP file', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating ZIP file'
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

    public function getPatientFormData()
    {
        try {

            Log::channel('patient_form')->info('Fetching patient form data started', [
                'user_id' => auth()->id()
            ]);

            $formSubmission = FormSubmission::with([
                    'form' => function ($query) {
                        $query->whereNull('deleted_at')
                            ->select('id', 'name', 'fields');
                    },
                    'funnel' => function ($query) {
                        $query->whereNull('deleted_at')
                            ->select('id', 'name');
                    },
                    'notes' => function ($query) {
                        $query->select(
                                'id',
                                'form_submission_id',
                                'note',
                                'noted_by',
                                'created_at',
                                'updated_at'
                            )
                            ->with('notedBy:id,name')
                            ->latest()
                            ->limit(1);
                    }
                ])
                ->where('user_id', auth()->id())
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->get()
                ->filter(function ($item) {
                    return $item->form && $item->funnel;
                });

            Log::channel('patient_form')->info('Patient form records fetched', [
                'user_id' => auth()->id(),
                'count'   => $formSubmission->count()
            ]);

            $data = $formSubmission->map(function ($item) {

                $decoded = [];

                if (!empty($item->form->fields['rows'])) {

                    foreach ($item->form->fields['rows'] as $row) {

                        foreach ($row['cols'] as $col) {

                            foreach ($col['fields'] as $field) {

                                $fieldId = $field['id'];

                                $decodedItem = [
                                    'type'     => $field['type'] ?? null,
                                    'label'    => $field['label'] ?? null,
                                    'required' => $field['required'] ?? false,
                                    'value'    => $item->data[$fieldId] ?? null,
                                ];

                                if (!empty($field['options'])) {
                                    $decodedItem['options'] = $field['options'];
                                }

                                $decoded[] = $decodedItem;
                            }
                        }
                    }
                }

                $latestNote = $item->notes->first();

                return [
                    'id'                       => $item->id,
                    'form_id'                  => $item->form_id,
                    'funnel_id'                => $item->funnel_id,
                    'form_name'                => $item->form->name ?? null,
                    'funnel_name'              => $item->funnel->name ?? null,
                    'status'                   => $item->status,
                    'created_at'               => $item->created_at,
                    'note_comments'            => $latestNote?->note,
                    'note_comments_update_at'  => $latestNote?->updated_at,
                    'pdf_url'                  => $item->pdf_url,
                    'downloadPdf'              => $item->pdf_url
                        ? url('/storage/form-pdfs/' . $item->pdf_url)
                        : null,
                    'decoded_json'             => $decoded,
                ];
            });

            Log::channel('patient_form')->info('Patient form data response prepared', [
                'user_id' => auth()->id(),
                'count'   => count($data)
            ]);

            return response()->json([
                'success' => true,
                'data'    => $data
            ], 200);

        } catch (\Throwable $e) {

            Log::channel('patient_form')->error('Error fetching patient form data', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
                'line'    => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching patient form data'
            ], 500);
        }
    }

    public function downloadPatientFormPdf(Request $request)
    {
        try {

            Log::channel('patient')->info('PDF download request started', [
                'user_id'  => auth()->id(),
                'pdf_urls' => $request->pdfUrls ?? []
            ]);

            $request->validate([
                'pdfUrls'   => 'required|array',
                'pdfUrls.*' => 'required|string'
            ]);

            $pdfFiles = $request->pdfUrls;

            $tempZip = tempnam(sys_get_temp_dir(), 'patient_forms_');

            Log::channel('patient')->info('Temporary ZIP file created', [
                'temp_zip_path' => $tempZip
            ]);

            $zip = new ZipArchive();

            if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {

                Log::channel('patient')->error('ZIP creation failed', [
                    'temp_zip_path' => $tempZip
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Could not create ZIP file'
                ], 500);
            }

            foreach ($pdfFiles as $fileName) {

                try {

                    Log::channel('patient')->info('Processing PDF file', [
                        'file_name' => $fileName
                    ]);

                    $storageFilePath = 'form-pdfs/' . $fileName;

                    if (!Storage::disk('public')->exists($storageFilePath)) {

                        Log::channel('patient')->warning('PDF file not found', [
                            'file_name' => $fileName,
                            'storage_path' => $storageFilePath
                        ]);

                        continue;
                    }

                    $fullPath = Storage::disk('public')->path($storageFilePath);

                    $zip->addFile($fullPath, $fileName);

                    Log::channel('patient')->info('PDF added to ZIP successfully', [
                        'file_name' => $fileName,
                        'full_path' => $fullPath
                    ]);

                } catch (\Exception $e) {

                    Log::channel('patient')->error('PDF add to ZIP failed', [
                        'file_name' => $fileName,
                        'error'     => $e->getMessage()
                    ]);

                    continue;
                }
            }

            $zip->close();

            Log::channel('patient')->info('ZIP file finalized successfully', [
                'temp_zip_path' => $tempZip
            ]);

            return response()->download(
                $tempZip,
                'patient_forms_pdfs_' . time() . '.zip'
            )->deleteFileAfterSend(true);

        } catch (\Throwable $e) {

            Log::channel('patient')->error('Download patient PDF failed', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error downloading PDF'
            ], 500);
        }
    }

}
