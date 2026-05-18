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
use Illuminate\Support\Facades\Mail;
use App\Mail\AssignFunnelMail;
use App\Models\AhcsPatient;
use App\Models\AhcsCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
                ->get(['id', 'name','form_ids']);

            $funnels->transform(function ($funnel) use ($request) {

                $formIds = is_array($funnel->form_ids)
                    ? $funnel->form_ids
                    : json_decode($funnel->form_ids ?? '[]', true);

                $formIds = is_array($formIds) ? $formIds : [];

                $totalForms = count($formIds);

                $submittedForms = FormSubmission::where('user_id', $request->user()->id)
                    ->where('funnel_id', $funnel->id)
                    ->whereIn('form_id', $formIds)
                    ->where('status', 'completed')
                    ->distinct('form_id')
                    ->count('form_id');

                $pendingCount = max($totalForms - $submittedForms, 0);

                return [
                    'id'                 => $funnel->id,
                    'funnel_name'        => $funnel->name,
                    'submission_status'  => $pendingCount === 0 ? 'completed' : 'pending',
                    'pending_count'      => $pendingCount,
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
            Log::channel('patient_form')->info('Fetching patient funnel submission details', [
                'user_id'   => auth()->id(),
                'funnel_id' => $funnelId
            ]);

            $funnelDetails = Funnel::where('id', $funnelId)
                ->first(['id', 'name', 'form_ids']);

            if (!$funnelDetails) {
                Log::channel('patient_form')->warning('Funnel submission details not found', [
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

            Log::channel('patient_form')->info('Patient funnel submission details fetched successfully', [
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
            Log::channel('patient_form')->error('Error fetching patient funnel submission details', [
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
            Log::channel('patient_form')->info('Patient form submission started', [
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
                Log::channel('patient_form')->warning('Patient form validation failed', [
                    'errors' => $validator->errors()
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $alreadySubmitted = FormSubmission::where('user_id', auth()->id())
                ->where('form_id', $formId)
                ->where('funnel_id', $request->funnel_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($alreadySubmitted) {

                return response()->json([
                    'success' => false,
                    'message' => 'Form already submitted.'
                ], 409);
            }

            // ── 2. Validate form exists ──────────────────────────────────────
            $form = Form::find($formId);
            if (!$form) {
                Log::channel('patient_form')->warning('Form not found', [
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
                        
                        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        $extension    = $file->getClientOriginalExtension();

                        $filename = $originalName . '_' . time() . '.' . $extension;

                        $path = $file->storeAs('form-uploads/' . $formId, $filename, 'public');
                        $formData[$fieldId] = $path;

                        Log::channel('patient_form')->info('File uploaded for form submission', [
                            'field_id' => $fieldId,
                            'file_path' => $path
                        ]);
                    }
                }
            }else{
                Log::channel('patient_form')->info('No file uploads received', [
                    'fields' => $formData
                ]);
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

            Log::channel('patient_form')->info('Patient form submitted successfully', [
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

                Log::channel('patient_form')->info('PDF generated for submission', [
                    'submission_id' => $submission->id,
                    'pdf_url'       => $pdfFilename,
                ]);

            } catch (\Throwable $e) {
                // PDF generation failure must NOT block the submission response
                Log::channel('patient_form')->error('PDF generation failed for submission #' . $submission->id, [
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
            Log::channel('patient_form')->error('Patient form submission failed', [
                'form_id' => $formId,
                'error'   => $e->getMessage(),
                'line'    => $e->getLine()
            ]);

            return response()->json([
                'status'  => false,
                'error' => $e->getMessage(),
                'message' => 'Something went wrong while submitting the form.',
            ], 500);
        }
    }


    public function getAllOldForms(){
        try{

            $allForms = DB::connection('patient_portal')->table('forms')->whereNull('deleted_at')->get();

            return response()->json([
                'status'  => true,
                'message' => 'Forms retrieved successfully.',
                'data'    => $allForms,
            ], 200);

        }catch(\Throwable $e){
            Log::channel('patient_form')->error('Error fetching all forms', [
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

    

    public function assignFunnel(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'patient_id'  => 'required|integer',
                'case_id'     => 'required|integer',
                'funnel_id'   => 'required|integer',
                'funnel_name' => 'required|string|max:255',
                'email'       => 'required|email',
                'phone'       => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Validate patient
            $patient = AhcsPatient::find($request->patient_id);
            if (!$patient) {
                return response()->json([
                    'status' => false,
                    'message' => 'Patient not found.',
                ], 404);
            }

            // Validate case
            $case = AhcsCase::find($request->case_id);
            if (!$case) {
                return response()->json([
                    'status' => false,
                    'message' => 'Case not found.',
                ], 404);
            }

            // Validate funnel
            $funnel = Funnel::find($request->funnel_id);
            if (!$funnel) {
                return response()->json([
                    'status' => false,
                    'message' => 'Funnel not found.',
                ], 404);
            }

            // Check user
            $user = User::where('patient_id', $request->patient_id)->first();
            $userId = $user?->id;
            $flag = $user ? 'user_exists' : 'no_user';
            $patientName = $patient->patient_name ?? $user?->name ?? 'Patient';

            // Send mail
            Mail::to($patient->email)->send(
                new AssignFunnelMail(
                    $request->patient_id,
                    $request->case_id,
                    $request->funnel_id,
                    $request->funnel_name,
                    $patientName,
                    $request->email ?? 'null',
                    $request->phone ?? 'null',
                    $flag
                )
            );

            // Check existing assignment by patient_id + funnel_id
            $existingAssignment = UserFunnel::withTrashed()
                ->where('patient_id', $request->patient_id)
                ->where('funnel_id', $request->funnel_id)
                ->first();

            // Fallback: also check by user_id + funnel_id (covers older records without patient_id)
            if (!$existingAssignment && $userId) {
                $existingAssignment = UserFunnel::withTrashed()
                    ->where('user_id', $userId)
                    ->where('funnel_id', $request->funnel_id)
                    ->first();
            }

            if (!$existingAssignment) {
                UserFunnel::create([
                    'user_id'      => $userId,
                    'patient_id'   => $request->patient_id,
                    'funnel_id'    => $request->funnel_id,
                    'assigned_via' => 'email',
                    'assigned_at'  => now(),
                ]);
            } elseif ($existingAssignment->trashed()) {
                $existingAssignment->restore();
                $existingAssignment->update([
                    'user_id'      => $userId,
                    'patient_id'   => $request->patient_id,
                    'assigned_via' => 'email',
                    'assigned_at'  => now(),
                ]);
            } else {
                $existingAssignment->update([
                    'user_id'      => $userId ?? $existingAssignment->user_id,
                    'patient_id'   => $request->patient_id,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Funnel assigned and email sent successfully.',
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Error assigning funnel via email', [
                'patient_id' => $request->patient_id ?? null,
                'funnel_id'  => $request->funnel_id ?? null,
                'message'    => $e->getMessage(),
                'line'       => $e->getLine(),
                'file'       => $e->getFile(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while assigning the funnel.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function addPatientToFunnel(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'patient_id'       => 'required|integer',
                // 'case_id'          => 'required|integer',
                'funnel_id'        => 'required|integer',
                'name'             => 'required|string|max:255',
                'email'            => 'required|email|max:255',
                'phone'            => 'nullable|string|max:20',
                'password'         => 'required|string|min:8',
                'confirm_password' => 'required|string|same:password',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // Check patient
            $patient = AhcsPatient::find($request->patient_id);

            if (!$patient) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Patient not found.',
                ], 404);
            }

            // Check funnel
            $funnel = Funnel::find($request->funnel_id);

            if (!$funnel) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Funnel not found.',
                ], 404);
            }

            // Check existing user
            $existingUser = User::where('email', $request->email)
                ->orWhere('patient_id', $request->patient_id)
                ->first();

            if ($existingUser) {
                return response()->json([
                    'status'  => false,
                    'message' => 'User already exists.',
                ], 409);
            }

            // Check funnel assignment
            $userFunnel = UserFunnel::where('patient_id', $request->patient_id)
                ->where('funnel_id', $request->funnel_id)
                ->first();

            if (!$userFunnel) {
                return response()->json([
                    'status'  => false,
                    'message' => 'User funnel assignment not found.',
                ], 404);
            }

            DB::beginTransaction();

            // Create user
            $user = User::create([
                'patient_id' => $request->patient_id,
                'name'       => $request->name,
                'email'      => $request->email,
                'phone'      => $request->phone,
                'password'   => bcrypt($request->password),
                'country_code' => 'US',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);

            // Update funnel assignment
            $userFunnel->update([
                'user_id' => $user->id,
            ]);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Patient added to funnel successfully.',
                'data'    => [
                    'user_id'    => $user->id,
                    'patient_id' => $user->patient_id,
                    'funnel_id'  => $request->funnel_id,
                ]
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Error adding patient to funnel', [
                'patient_id' => $request->patient_id ?? null,
                'funnel_id'  => $request->funnel_id ?? null,
                'message'    => $e->getMessage(),
                'line'       => $e->getLine(),
                'file'       => $e->getFile(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while adding patient to the funnel.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllFunnelList(){
        try{
            $funnels = Funnel::where('status', 'active')->get(['id', 'name']);

            return response()->json([
                'status' => true,
                'message' => 'Funnels retrieved successfully.',
                'data' => $funnels,
            ], 200);
        }catch(\Throwable $e){
            Log::error('Error fetching funnel list', [
                'message'    => $e->getMessage(),
                'line'       => $e->getLine(),
                'file'       => $e->getFile(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while fetching funnels.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
