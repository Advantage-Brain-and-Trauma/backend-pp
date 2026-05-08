<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Funnel;
use App\Models\User;
use App\Models\UserFunnel;
use App\Services\FormSubmissionPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class FunnelApiController extends Controller
{
    /**
     * GET /api/get-patient-funnels
     *
     * Returns funnels assigned to the authenticated user.
     */
    public function getPatientFunnels(Request $request)
    {
        try {
            Log::channel('patient_funnel')->info('Fetching patient funnels - Start', [
                'user_id' => $request->user()->id
            ]);

            $userFunnels = UserFunnel::where('user_id', $request->user()->id)
                ->pluck('funnel_id');

            Log::channel('patient_funnel')->info('User funnel IDs fetched', [
                'funnel_ids' => $userFunnels
            ]);

            $funnels = Funnel::whereIn('id', $userFunnels)
                ->where('status', 'active')
                ->get(['id', 'name']);

            $funnels->transform(function ($funnel) {
                return [
                    'id'          => $funnel->id,
                    'funnel_name' => $funnel->name,
                ];
            });

            Log::channel('patient_funnel')->info('Fetching patient funnels - Success', [
                'user_id'       => $request->user()->id,
                'total_funnels' => $funnels->count()
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Funnels retrieved successfully.',
                'data'    => $funnels,
            ], 200);

        } catch (\Throwable $e) {
            Log::channel('patient_funnel')->error('Error fetching patient funnels', [
                'user_id' => $request->user()->id ?? null,
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'status'  => false,
                'error'   => $e->getMessage(),
                'message' => 'Error fetching patient funnels',
            ], 500);
        }
    }

    /**
     * GET /api/get-patient-funnel-submission-details/{funnelId}
     *
     * Returns funnel details with per-form submission status for the authenticated user.
     */
    public function getPatientFunnelSubmissionDetails($funnelId)
    {
        try {
            Log::channel('patient_portal')->info('Fetching patient funnel submission details', [
                'user_id'   => auth()->id(),
                'funnel_id' => $funnelId
            ]);

            $funnelDetails = Funnel::where('id', $funnelId)
                ->first(['id', 'name', 'form_ids']);

            if (!$funnelDetails) {
                Log::channel('patient_portal')->warning('Funnel submission details not found', [
                    'funnel_id' => $funnelId
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Funnel not found',
                ], 404);
            }

            $formIds = is_array($funnelDetails->form_ids)
                ? $funnelDetails->form_ids
                : json_decode($funnelDetails->form_ids, true);

            $formDetails = Form::whereIn('id', $formIds)
                ->orderByRaw("FIELD(id, " . implode(',', $formIds) . ")")
                ->get(['id', 'name', 'description', 'fields']);

            $submissions = FormSubmission::whereIn('form_id', $formIds)
                ->where('user_id', auth()->id())
                ->where('funnel_id', $funnelId)
                ->get(['form_id', 'status']);

            $forms = $formDetails->map(function ($form) use ($submissions) {

                $submission = $submissions->where('form_id', $form->id)->first();

                // Extract only fields array
                $onlyFields = collect($form->fields['rows'] ?? [])
                    ->flatMap(function ($row) {
                        return collect($row['cols'] ?? [])
                            ->flatMap(function ($col) {
                                return $col['fields'] ?? [];
                            });
                    })
                    ->values();

                return [
                    'id'                => $form->id,
                    'name'              => $form->name,
                    'description'       => $form->description,
                    'submission_status' => $submission ? $submission->status : null,
                    'fields'            => $onlyFields,
                ];
            });

            Log::channel('patient_portal')->info('Patient funnel submission details fetched successfully', [
                'funnel_id'   => $funnelId,
                'forms_count' => $forms->count()
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Funnel submission details retrieved successfully.',
                'data'    => [
                    'id'          => $funnelDetails->id,
                    'funnel_name' => $funnelDetails->name,
                    'forms'       => $forms,
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::channel('patient_portal')->error('Error fetching patient funnel submission details', [
                'funnel_id' => $funnelId,
                'message'   => $e->getMessage(),
                'line'      => $e->getLine()
            ]);

            return response()->json([
                'status'  => false,
                'error'   => $e->getMessage(),
                'message' => 'Error fetching patient form data',
            ], 500);
        }
    }

    /**
     * POST /api/patient-forms/{formId}/submit
     *
     * Store form submission data into form_submissions table, then generate
     * a PDF of the submitted form and save its filename in pdf_url.
     *
     * Request body (multipart/form-data or application/json):
     *   funnel_id  (required) integer  - ID of the funnel this form belongs to
     *   fields     (required) object   - Key-value pairs of field_id => value
     *                                    For file fields, send the file under fields[fieldId]
     *
     * Example JSON body:
     * {
     *   "funnel_id": 1,
     *   "fields": {
     *     "f1": "John Doe",
     *     "f2": "john@example.com",
     *     "f3": "5551234567",
     *     "f4": ["Option 1", "Option 3"]
     *   }
     * }
     */
    public function PatientSubmitForm(Request $request, int $formId)
    {
        try {
            Log::channel('patient_portal')->info('Patient form submission started', [
                'user_id'   => auth()->id(),
                'form_id'   => $formId,
                'funnel_id' => $request->input('funnel_id')
            ]);

            // ── 1. Validate request ──────────────────────────────────────────
            $validator = Validator::make($request->all(), [
                'funnel_id' => 'required|integer|exists:funnels,id',
                'fields'    => 'required|array',
            ]);

            if ($validator->fails()) {
                Log::channel('patient_portal')->warning('Patient form validation failed', [
                    'errors' => $validator->errors()
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // ── 2. Validate form exists ──────────────────────────────────────
            $form = Form::find($formId);
            if (!$form) {
                Log::channel('patient_portal')->warning('Form not found', [
                    'form_id' => $formId
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Form not found.',
                ], 404);
            }

            // ── 3. Collect field data ────────────────────────────────────────
            $formData = $request->input('fields', []);

            // Handle file uploads (multipart/form-data)
            if ($request->hasFile('fields')) {
                foreach ($request->file('fields') as $fieldId => $file) {
                    if ($file && $file->isValid()) {
                        $path = $file->store('form-uploads/' . $formId, 'public');
                        $formData[$fieldId] = $path;
                    }
                }
            }

            // ── 4. Determine submission status ───────────────────────────────
            $hasData = collect($formData)
                ->filter(fn($v) => $v !== null && $v !== '' && $v !== [])
                ->isNotEmpty();

            // ── 5. Save submission ───────────────────────────────────────────
            $submission = FormSubmission::create([
                'user_id'    => auth()->id(),
                'form_id'    => $formId,
                'funnel_id'  => $request->input('funnel_id'),
                'data'       => $formData,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status'     => $hasData ? 'completed' : 'draft',
            ]);

            Log::channel('patient_portal')->info('Patient form submitted successfully', [
                'submission_id' => $submission->id,
                'form_id'       => $submission->form_id,
                'funnel_id'     => $submission->funnel_id,
                'status'        => $submission->status
            ]);

            // ── 6. Generate PDF and save filename ────────────────────────────
            $pdfFilename = null;
            try {
                /** @var User|null $user */
                $user        = Auth::user();
                $pdfService  = new FormSubmissionPdfService();
                $pdfFilename = $pdfService->generate($submission, $form, $user);

                $submission->pdf_url = $pdfFilename;
                $submission->save();

                Log::channel('patient_portal')->info('PDF generated for submission', [
                    'submission_id' => $submission->id,
                    'pdf_url'       => $pdfFilename,
                ]);

            } catch (\Throwable $e) {
                // PDF generation failure must NOT block the submission response
                Log::channel('patient_portal')->error('PDF generation failed for submission #' . $submission->id, [
                    'error' => $e->getMessage(),
                    'line'  => $e->getLine(),
                    'file'  => $e->getFile(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            // ── 7. Return response ───────────────────────────────────────────
            return response()->json([
                'status'  => true,
                'message' => 'Form submitted successfully.',
                'data'    => [
                    'submission_id' => $submission->id,
                    'form_id'       => $submission->form_id,
                    'funnel_id'     => $submission->funnel_id,
                    'status'        => $submission->status,
                    'pdf_url'       => $pdfFilename,
                    'submitted_at'  => $submission->created_at->toISOString(),
                ],
            ], 201);

        } catch (\Throwable $e) {
            Log::channel('patient_portal')->error('Patient form submission failed', [
                'form_id' => $formId,
                'error'   => $e->getMessage(),
                'line'    => $e->getLine()
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while submitting the form.',
            ], 500);
        }
    }


    public function getAllOldForms(){
        try{

            $allForms = DB::connection('patient_portal')->table('forms')->get();

            return response()->json([
                'status'  => true,
                'message' => 'Forms retrieved successfully.',
                'data'    => $allForms,
            ], 200);

        }catch(\Throwable $e){
            Log::channel('patient_portal')->error('Error fetching all forms', [
                'error'   => $e->getMessage(),
                'line'    => $e->getLine()
            ]);

            return response()->json([
                'status'  => false,
                'error' => $e->getMessage(),
                'message' => 'Something went wrong while fetching forms.',
            ], 500);
        }
    }
}
