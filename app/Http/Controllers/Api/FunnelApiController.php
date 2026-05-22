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
use App\Models\PatientCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FunnelApiController extends Controller
{
    /**
     * GET /api/get-patient-funnels
     *
     * Returns funnels assigned to the authenticated user.
     *
     * Request Payload:
     * - None (uses authenticated user and JWT case_id claim when available)
     *
     * Response:
     * - 200: { status: true, message: string, data: [{ id, funnel_name, submission_status, pending_count }] }
     * - 500: { status: false, message: string }
     */
    

    public function getPatientFunnels(Request $request)
    {
        try {

            $caseId = auth()->payload()->get('case_id');

            Log::channel('patient_funnel')->info('Fetching patient funnels - Start', [
                'user_id' => $request->user()->id,
                'case_id' => $caseId,
            ]);

            $userFunnelsQuery = UserFunnel::where('user_id', $request->user()->id);

            // Apply case_id filter only if provided
            if (!empty($caseId)) {
                $userFunnelsQuery->whereHas('patientCase', function ($q) use ($caseId) {
                    $q->where('case_id', $caseId);
                });
            }

            $userFunnels = $userFunnelsQuery->pluck('funnel_id');

            Log::channel('patient_funnel')->info('User funnel IDs fetched', [
                'funnel_ids' => $userFunnels
            ]);

            $funnels = Funnel::whereIn('id', $userFunnels)
                ->where('status', 'active')
                ->get(['id', 'name', 'form_ids']);

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
                    'id'                => $funnel->id,
                    'funnel_name'       => $funnel->name,
                    'submission_status' => $pendingCount === 0 ? 'completed' : 'pending',
                    'pending_count'     => $pendingCount,
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
                'message' => 'Error fetching patient funnels',
            ], 500);
        }
    }

    /**
     * GET /api/get-patient-funnel-submission-details/{funnelId}
     *
     * Returns funnel details with per-form submission status for the authenticated user.
     *
     * Request Payload:
     * - Path param: funnelId (int)
     * - No JSON body (uses authenticated user and JWT case_id claim when available)
     *
     * Response:
     * - 200: { status: true, message: string, data: { id, funnel_name, forms: [{ id, name, description, submission_status, fields }] } }
     * - 404: { status: false, message: string } (patient/funnel/case mapping not found)
     * - 500: { status: false, message: string }
     */

    public function getPatientFunnelSubmissionDetails($funnelId)
    {
        try {
            $userId = auth()->id();
            $caseId = auth()->payload()->get('case_id');
            $patientId = auth()->user()->patient_id;

            $fieldMapping = [
                "first_name" => "First Name:",
                "last_name" => "Last Name:",
                "middle_name" => "Middle Name:",
                "suffix" => "Suffix",
                "dob" => "Date of Birth:",
                "ssn" => "SSN:",
                "driver_license" => "Driver’s License Number:",
                "dl_state" => "DL State",
                "sex" => "Sex:",
                "address1" => "Physical Address:",
                "address2" => "Apartment/Suite Number:",
                "city" => "City:",
                "state" => "State:",
                "zip" => "Zip Code:",
                "mailing_address" => "Mailing Address (if different from physical address):",
                "mailing_address2" => "Apt/Suite # (Mailing)",
                "city2" => "City (Mailing)",
                "state2" => "State (Mailing)",
                "zip2" => "Zip (Mailing)",
                "home_ph" => "Phone Number:",
                "work_ph" => "Work Phone:",
                "work_ext" => "Work Extension:",
                "cell_no" => "Mobile",
                "wireless_carrier" => "Wireless Carrier",
                "textmsg_consent" => "Text Messages?",
                "fax_no" => "Fax Number:",
                "email" => "Email:",
                "marital_status" => "Marital Status:",
                "children" => "Number of Children:",
                "ethnicity" => "Ethnicity:",
                "language" => "Primary Language:",
                "education" => "Education:",
                "hand_dom" => "Hand Dominance",
                "emerg_contact" => "Name",
                "emerg_address" => "Address",
                "emerg_city" => "City",
                "emerg_state" => "State",
                "emerg_zip" => "Zip",
                "emerg_phone" => "Phone",
                "emerg_cell" => "Mobile",
                "emerg_relation" => "Relationship",
                "allergy" => "Allergies"
            ];

            $translationMap = [
                "First Name:" => "El Nombre de Pila:",
                "Middle Name:" => "El Segundo Nombre:",
                "Last Name:" => "El Apellido:",
                "Date of Birth:" => "La Fecha de Nacimiento:",
                "SSN:" => "El Numero de Seguridad Social:",
                "Sex:" => "Sex:",
                "Physical Address:" => "Le Direccion Fisica:",
                "Apartment/Suite Number:" => "El Apartamento/Numero de Suite:",
                "City:" => "La Ciudad:",
                "State:" => "El Estado:",
                "Zip Code:" => "El Codigo Postal:",
                "Mailing Address (if different from physical address):" => "Dirección postal (si es diferente de la dirección física):",
                "Phone Number:" => "numero de telefono:",
                "Work Phone:" => "telefono del trabajo",
                "Work Extension:" => "Extensión de trabajo:",
                "Mobile" => "Número de teléfono móvil:",
                "Fax Number:" => "El numero de fax:",
                "Email:" => "El correo electronico:",
                "Driver’s License Number:" => "Número de licencia de conducir:",
                "Marital Status:" => "El estado civil:",
                "Number of Children:" => "Número de niños:",
                "Ethnicity:" => "la etnia:",
                "Education:" => "educacion:",
                "Hand Dominance" => "Dominación de la mano",
                "Primary Language:" => "Idioma principal:",
                "Name" => "Nombre",
                "Address" => "La Direccion",
                "City" => "Ciudad",
                "Phone" => "Telephono",
                "Relationship" => "relacion",
                "Allergies" => "Alergias"
            ];

            $patient = AhcsPatient::where('id', $patientId)->first();

            if (!$patient) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Patient not found',
                ], 404);
            }

            $patientValues = [];

            foreach ($fieldMapping as $patientColumn => $englishLabel) {
                $patientValues[trim($englishLabel)] = $patient->{$patientColumn} ?? null;

                if (isset($translationMap[$englishLabel])) {
                    $patientValues[trim($translationMap[$englishLabel])] = $patient->{$patientColumn} ?? null;
                }
            }

            Log::channel('patient_form')->info('Fetching patient funnel submission details', [
                'user_id'    => $userId,
                'funnel_id'  => $funnelId,
                'patient_id' => $patientId,
                'case_id'    => $caseId,
            ]);

            $userFunnelQuery = UserFunnel::where('user_id', $userId)
                ->where('funnel_id', $funnelId);

            if (!empty($caseId)) {
                $userFunnelQuery->whereHas('patientCase', function ($q) use ($caseId, $patientId) {
                    $q->where('case_id', $caseId)
                    ->where('patient_id', $patientId);
                });
            }

            $userFunnel = $userFunnelQuery->first();

            if (!$userFunnel) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Funnel not found for this patient case',
                ], 404);
            }

            $funnelDetails = Funnel::where('id', $funnelId)
                ->where('status', 'active')
                ->first(['id', 'name', 'form_ids']);

            if (!$funnelDetails) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Funnel not found',
                ], 404);
            }

            $formIds = is_array($funnelDetails->form_ids)
                ? $funnelDetails->form_ids
                : json_decode($funnelDetails->form_ids ?? '[]', true);

            $formIds = is_array($formIds) ? $formIds : [];

            if (empty($formIds)) {
                return response()->json([
                    'status'  => true,
                    'message' => 'Funnel submission details retrieved successfully.',
                    'data'    => [
                        'id'          => $funnelDetails->id,
                        'funnel_name' => $funnelDetails->name,
                        'forms'       => [],
                    ],
                ], 200);
            }

            $formDetails = Form::whereIn('id', $formIds)
                ->orderByRaw("FIELD(id, " . implode(',', $formIds) . ")")
                ->get(['id', 'name', 'description', 'fields']);

            $submissions = FormSubmission::whereIn('form_id', $formIds)
                ->where('user_id', $userId)
                ->where('funnel_id', $funnelId)
                ->get(['form_id', 'status']);

            $forms = $formDetails->map(function ($form) use ($submissions, $patientValues) {
                $submission = $submissions->where('form_id', $form->id)->first();

                $fields = is_array($form->fields)
                    ? $form->fields
                    : json_decode($form->fields ?? '[]', true);

                $onlyFields = collect($fields['rows'] ?? [])
                    ->flatMap(function ($row) use ($patientValues) {
                        return collect($row['cols'] ?? [])
                            ->flatMap(function ($col) use ($patientValues) {
                                return collect($col['fields'] ?? [])->map(function ($field) use ($patientValues) {

                                    $label = trim($field['label'] ?? '');
                                    $value = $patientValues[$label] ?? null;

                                    $newField = [];

                                    foreach ($field as $key => $item) {

                                        $newField[$key] = $item;

                                        if ($key === 'label') {
                                            $newField['value'] = $value;
                                        }
                                    }

                                    return $newField;
                                });
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
                'line'      => $e->getLine(),
                'file'      => $e->getFile(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Error fetching patient form data',
            ], 500);
        }
    }

    /**
     * POST /api/patient-submit-form/{formId}
     *
     * Submits a patient form for the authenticated user and generates a PDF when possible.
     *
     * Request Payload:
     * - Path param: formId (int)
     * - Body:
     *   - funnel_id (required, int)
     *   - fields (required, array; supports scalar values and file uploads)
     *
     * Response:
     * - 201: { status: true, message: string, data: { submission_id, form_id, funnel_id, status, pdf_url, submitted_at } }
     * - 403: { status: false, message: string } (invalid patient/case)
     * - 404: { status: false, message: string } (form not found)
     * - 409: { status: false, message: string } (already submitted)
     * - 422: { status: false, message: string, errors: object }
     * - 500: { status: false, message: string }
     */
    public function patientSubmitForm(Request $request, int $formId)
    {
        try {

            $userId    = auth()->id();
            $patientId = auth()->user()->patient_id;
            $caseId    = auth()->payload()->get('case_id');
            
            Log::channel('patient_form')->info('Patient form submission started', [
                'user_id'    => $userId,
                'patient_id' => $patientId,
                'case_id'    => $caseId,
                'form_id'    => $formId,
                'funnel_id'  => $request->funnel_id,
                'ip_address' => $request->ip(),
            ]);

            $validator = Validator::make($request->all(), [
                'funnel_id' => 'required|integer|exists:funnels,id',
                'fields'    => 'required|array',
            ]);

            if ($validator->fails()) {

            Log::channel('patient_form')->warning('Patient form validation failed', [
                'user_id' => $userId,
                'errors'  => $validator->errors()->toArray(),
                'payload' => $request->all(),
            ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $checkPatientCase = AhcsCase::where('id', $caseId)
                ->where('patient_id', $patientId)
                ->exists();

            if (!$checkPatientCase) {
                Log::channel('patient_form')->warning('Invalid patient or case', [
                    'user_id'    => $userId,
                    'patient_id' => $patientId,
                    'case_id'    => $caseId,
                ]);
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid patient or case',
                ], 403);
            }

            $alreadySubmitted = FormSubmission::where('user_id', $userId)
                ->where('form_id', $formId)
                ->where('funnel_id', $request->funnel_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($alreadySubmitted) {
                Log::channel('patient_form')->warning('Form already submitted', [
                    'user_id'   => $userId,
                    'form_id'   => $formId,
                    'funnel_id' => $request->funnel_id,
                ]);
                return response()->json([
                    'status'  => false,
                    'message' => 'Form already submitted.',
                ], 409);
            }

            $form = Form::find($formId);

            if (!$form) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Form not found.',
                ], 404);
            }

            $submission = null;
            $pdfFilename = null;

            DB::beginTransaction();

            try {
                $fieldsInput = $request->input('fields', []);
                $formData = [];

                foreach ($fieldsInput as $fieldId => $fieldValue) {
                    if (is_array($fieldValue)) {
                        $formData[$fieldId] = $fieldValue['value'] ?? null;
                    } else {
                        $formData[$fieldId] = $fieldValue;
                    }
                }

                if ($request->hasFile('fields')) {
                    foreach ($request->file('fields') as $fieldId => $file) {
                        if ($file && $file->isValid()) {
                            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                            $extension    = $file->getClientOriginalExtension();
                            $filename     = $originalName . '_' . time() . '.' . $extension;

                            $path = $file->storeAs(
                                'form-uploads/' . $formId,
                                $filename,
                                'public'
                            );

                            $formData[$fieldId] = $path;
                        }
                    }
                }

                $fieldMapping = [
                    "first_name" => "First Name:",
                    "last_name" => "Last Name:",
                    "middle_name" => "Middle Name:",
                    "suffix" => "Suffix",
                    "dob" => "Date of Birth:",
                    "ssn" => "SSN:",
                    "driver_license" => "Driver’s License Number:",
                    "dl_state" => "DL State",
                    "sex" => "Sex:",
                    "address1" => "Physical Address:",
                    "address2" => "Apartment/Suite Number:",
                    "city" => "City:",
                    "state" => "State:",
                    "zip" => "Zip Code:",
                    "mailing_address" => "Mailing Address (if different from physical address):",
                    "mailing_address2" => "Apt/Suite # (Mailing)",
                    "city2" => "City (Mailing)",
                    "state2" => "State (Mailing)",
                    "zip2" => "Zip (Mailing)",
                    "home_ph" => "Phone Number:",
                    "work_ph" => "Work Phone:",
                    "work_ext" => "Work Extension:",
                    "cell_no" => "Mobile",
                    "wireless_carrier" => "Wireless Carrier",
                    "textmsg_consent" => "Text Messages?",
                    "fax_no" => "Fax Number:",
                    "email" => "Email:",
                    "marital_status" => "Marital Status:",
                    "children" => "Number of Children:",
                    "ethnicity" => "Ethnicity:",
                    "language" => "Primary Language:",
                    "education" => "Education:",
                    "hand_dom" => "Hand Dominance",
                    "emerg_contact" => "Name",
                    "emerg_address" => "Address",
                    "emerg_city" => "City",
                    "emerg_state" => "State",
                    "emerg_zip" => "Zip",
                    "emerg_phone" => "Phone",
                    "emerg_cell" => "Mobile",
                    "emerg_relation" => "Relationship",
                    "allergy" => "Allergies",
                ];

                $translationMap = [
                    "First Name:" => "El Nombre de Pila:",
                    "Middle Name:" => "El Segundo Nombre:",
                    "Last Name:" => "El Apellido:",
                    "Date of Birth:" => "La Fecha de Nacimiento:",
                    "SSN:" => "El Numero de Seguridad Social:",
                    "Sex:" => "Sex:",
                    "Physical Address:" => "Le Direccion Fisica:",
                    "Apartment/Suite Number:" => "El Apartamento/Numero de Suite:",
                    "City:" => "La Ciudad:",
                    "State:" => "El Estado:",
                    "Zip Code:" => "El Codigo Postal:",
                    "Mailing Address (if different from physical address):" => "Dirección postal (si es diferente de la dirección física):",
                    "Phone Number:" => "numero de telefono:",
                    "Work Phone:" => "telefono del trabajo",
                    "Work Extension:" => "Extensión de trabajo:",
                    "Mobile" => "Número de teléfono móvil:",
                    "Fax Number:" => "El numero de fax:",
                    "Email:" => "El correo electronico:",
                    "Driver’s License Number:" => "Número de licencia de conducir:",
                    "Marital Status:" => "El estado civil:",
                    "Number of Children:" => "Número de niños:",
                    "Ethnicity:" => "la etnia:",
                    "Education:" => "educacion:",
                    "Hand Dominance" => "Dominación de la mano",
                    "Primary Language:" => "Idioma principal:",
                    "Name" => "Nombre",
                    "Address" => "La Direccion",
                    "City" => "Ciudad",
                    "Phone" => "Telephono",
                    "Relationship" => "relacion",
                    "Allergies" => "Alergias",
                ];

                $labelToColumn = [];

                foreach ($fieldMapping as $column => $englishLabel) {
                    $labelToColumn[trim($englishLabel)] = $column;

                    if (isset($translationMap[$englishLabel])) {
                        $labelToColumn[trim($translationMap[$englishLabel])] = $column;
                    }
                }

                $patientUpdateData = [];

                foreach ($fieldsInput as $field) {
                    if (!is_array($field)) {
                        continue;
                    }

                    $label = trim($field['label'] ?? $field['lable'] ?? '');
                    $value = $field['value'] ?? null;

                    if ($label && array_key_exists($label, $labelToColumn)) {
                        $patientUpdateData[$labelToColumn[$label]] = $value;
                    }
                }
                $existingPatient = AhcsPatient::find($patientId);
                if (!empty($patientUpdateData)) {
                    Log::channel('patient_form')->info('Updating patient data', [
                        'patient_id' => $patientId,
                        'old_data' => optional($existingPatient)->toArray(),
                        'data'       => $patientUpdateData,
                    ]);
                    AhcsPatient::where('id', $patientId)->update($patientUpdateData);
                }

                $hasData = collect($formData)
                    ->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])
                    ->isNotEmpty();

                Log::channel('patient_form')->info('Creating form submission', [
                    'user_id'   => $userId,
                    'form_id'   => $formId,
                    'funnel_id' => $request->input('funnel_id'),
                    'data'      => $formData,
                    'status'    => $hasData ? 'completed' : 'draft',
                ]);

                $submission = FormSubmission::create([
                    'user_id'    => $userId,
                    'form_id'    => $formId,
                    'funnel_id'  => $request->input('funnel_id'),
                    'data'       => $formData,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'status'     => $hasData ? 'completed' : 'draft',
                ]);

                DB::commit();

            } catch (\Throwable $e) {
                DB::rollBack();

                Log::channel('patient_form')->error('Form submission transaction failed', [
                    'user_id'    => $userId,
                    'patient_id' => $patientId,
                    'case_id'    => $caseId,
                    'form_id'    => $formId,
                    'error'      => $e->getMessage(),
                ]);

                throw $e;
            }

            try {
                $user = Auth::user();
                $pdfService = new FormSubmissionPdfService();

                $pdfFilename = $pdfService->generate($submission, $form, $user);

                $submission->pdf_url = $pdfFilename;
                $submission->save();

            } catch (\Throwable $e) {
                Log::channel('patient_form')->error('PDF generation failed', [
                    'submission_id' => $submission?->id,
                    'error'         => $e->getMessage(),
                ]);
            }

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

            Log::channel('patient_form')->info('Patient form submitted successfully', [
                'submission_id' => $submission->id,
                'pdf_url'       => $pdfFilename,
            ]);

        } catch (\Throwable $e) {
            Log::channel('patient_form')->error('Patient form submission failed', [
                'form_id' => $formId,
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while submitting the form.',
            ], 500);
        }
    }

    /**
     * GET /api/get-all-old-forms
     *
     * Returns legacy forms from the patient_portal database connection.
     *
     * Request Payload:
     * - None
     *
     * Response:
     * - 200: { status: true, message: string, data: Form[] }
     * - 500: { status: false, message: string, error: string }
     */
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

    /**
     * POST /api/assign-funnel
     *
     * Assigns a funnel to a patient/case and sends the assignment email.
     *
     * Request Payload:
     * - patient_id (required, int, exists in ahcs.ahcs_patients)
     * - case_id (required, int, exists in ahcs.ahcs_cases)
     * - funnel_id (required, int, exists in funnels)
     * - funnel_name (required, string)
     * - email (required, valid email)
     * - phone (optional, string)
     *
     * Response:
     * - 200: { status: true, message: string }
     * - 422: { status: false, message: string, errors: string }
     * - 500: { status: false, message: string }
     */
    public function assignFunnel(Request $request)
    {
        try {
            Log::channel('patient_funnel')->info('Assign funnel request received', [
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
                'funnel_id'  => $request->funnel_id,
            ]);

            $validator = Validator::make($request->all(), [
                'patient_id'  => 'required|integer|exists:ahcs.ahcs_patients,id',
                'case_id'     => 'required|integer|exists:ahcs.ahcs_cases,id',
                'funnel_id'   => 'required|integer|exists:funnels,id',
                'funnel_name' => 'required|string|max:255',
                'email'       => 'required|email',
                'phone'       => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                Log::channel('patient_funnel')->warning('Assign funnel validation failed', [
                    'patient_id' => $request->patient_id,
                    'case_id'    => $request->case_id,
                    'funnel_id'  => $request->funnel_id,
                    'error'      => $validator->errors()->first(),
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors()->first(),
                ], 422);
            }

            DB::beginTransaction();

            $patient = AhcsPatient::find($request->patient_id);

            $case = AhcsCase::find($request->case_id);

            $funnel = Funnel::find($request->funnel_id);

            $user = User::where('patient_id', $request->patient_id)->first();
            $userId = $user?->id;
            $flag = $user ? 'user_exists' : 'no_user';
            $patientName = $patient->patient_name
                ?? $user?->name
                ?? 'Patient';

            // Create patient case if not exists
            $patientCase = PatientCase::firstOrCreate([
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
            ]);

            // Check existing funnel assignment
            $existingAssignment = UserFunnel::withTrashed()
                ->where('patient_id', $request->patient_id)
                ->where('funnel_id', $request->funnel_id)
                ->first();

            // Fallback check using user_id
            if (!$existingAssignment && $userId) {

                $existingAssignment = UserFunnel::withTrashed()
                    ->where('user_id', $userId)
                    ->where('funnel_id', $request->funnel_id)
                    ->first();
            }

            // Create / Restore / Update
            if (!$existingAssignment) {
                UserFunnel::create([
                    'user_id'         => $userId,
                    'patient_id'      => $request->patient_id,
                    'funnel_id'       => $request->funnel_id,
                    'patient_case_id' => $patientCase->id,
                    'assigned_via'    => 'email',
                    'assigned_at'     => now(),
                ]);

            } elseif ($existingAssignment->trashed()) {
                $existingAssignment->restore();
                $existingAssignment->update([
                    'user_id'         => $userId,
                    'patient_id'      => $request->patient_id,
                    'patient_case_id' => $patientCase->id,
                    'assigned_via'    => 'email',
                    'assigned_at'     => now(),
                ]);

            } else {
                $existingAssignment->update([
                    'user_id'         => $userId ?? $existingAssignment->user_id,
                    'patient_id'      => $request->patient_id,
                    'patient_case_id' => $patientCase->id,
                ]);
            }

            // Send email
            Mail::to($request->email)->send(
                new AssignFunnelMail(
                    $request->patient_id,
                    $request->case_id,
                    $request->funnel_id,
                    $request->funnel_name,
                    $patientName,
                    $request->email,
                    $request->phone,
                    $flag
                )
            );
            DB::commit();

            Log::channel('patient_funnel')->info('Funnel assigned successfully', [
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
                'funnel_id'  => $request->funnel_id,
                'user_id'    => $userId,
                'flag'       => $flag,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Funnel assigned and email sent successfully.',
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::channel('patient_funnel')->error('Error assigning funnel', [
                'patient_id' => $request->patient_id ?? null,
                'case_id'    => $request->case_id ?? null,
                'funnel_id'  => $request->funnel_id ?? null,
                'message'    => $e->getMessage(),
                'line'       => $e->getLine(),
                'file'       => $e->getFile(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while assigning the funnel.',
            ], 500);
        }
    }

    /**
     * POST /api/add-patient-to-funnel
     *
     * Creates or updates a portal user for a patient and binds the user to an assigned funnel.
     *
     * Request Payload:
     * - patient_id (required, int)
     * - case_id (required, int)
     * - funnel_id (required, int)
     * - name (required, string)
     * - email (required, valid email)
     * - phone (optional, string)
     * - password (required, string, min:8)
     * - confirm_password (required, string, same as password)
     *
     * Response:
     * - 200: { status: true, message: string, data: { user_id, patient_id, funnel_id } }
     * - 404: { status: false, message: string } (patient/case/funnel/assignment not found)
     * - 422: { status: false, message: string }
     * - 500: { status: false, message: string }
     */
    public function addPatientToFunnel(Request $request)
    {
        try {
            Log::channel('patient_funnel')->info('Add patient to funnel request received', [
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
                'funnel_id'  => $request->funnel_id,
            ]);

            $validator = Validator::make($request->all(), [
                'patient_id'       => 'required|integer',
                'case_id'          => 'required|integer',
                'funnel_id'        => 'required|integer',
                'name'             => 'required|string|max:255',
                'email'            => 'required|email|max:255',
                'phone'            => 'nullable|string|max:20',
                'password'         => 'required|string|min:8',
                'confirm_password' => 'required|string|same:password',
            ]);

            if ($validator->fails()) {
                Log::channel('patient_funnel')->warning('Add patient to funnel validation failed', [
                    'patient_id' => $request->patient_id,
                    'case_id'    => $request->case_id,
                    'funnel_id'  => $request->funnel_id,
                    'error'      => $validator->errors()->first(),
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => $validator->errors()->first(),
                    // 'errors'  => $validator->errors(),
                ], 422);
            }

            // Check patient
            $patient = AhcsPatient::find($request->patient_id);

            if (!$patient) {
                Log::channel('patient_funnel')->warning('Add patient to funnel failed: patient not found', [
                    'patient_id' => $request->patient_id,
                    'funnel_id'  => $request->funnel_id,
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Patient not found.',
                ], 404);
            }

            $case = AhcsCase::find($request->case_id);

            if (!$case) {
                Log::channel('patient_funnel')->warning('Add patient to funnel failed: case not found', [
                    'patient_id' => $request->patient_id,
                    'case_id'    => $request->case_id,
                    'funnel_id'  => $request->funnel_id,
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Case not found.',
                ], 404);
            }

            // Check funnel
            $funnel = Funnel::find($request->funnel_id);

            if (!$funnel) {
                Log::channel('patient_funnel')->warning('Add patient to funnel failed: funnel not found', [
                    'patient_id' => $request->patient_id,
                    'funnel_id'  => $request->funnel_id,
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Funnel not found.',
                ], 404);
            }

            // Check funnel assignment
            $userFunnel = UserFunnel::where('patient_id', $request->patient_id)
                ->where('funnel_id', $request->funnel_id)
                ->first();

            if (!$userFunnel) {
                Log::channel('patient_funnel')->warning('Add patient to funnel failed: assignment not found', [
                    'patient_id' => $request->patient_id,
                    'funnel_id'  => $request->funnel_id,
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'User funnel assignment not found.',
                ], 404);
            }

            DB::beginTransaction();

            // Find existing user by email or patient_id (including soft-deleted records)
            $user = User::withTrashed()
                ->where('email', $request->email)
                ->orWhere('patient_id', $request->patient_id)
                ->first();

            if ($user) {
                // Restore soft-deleted user if needed and update their details
                if ($user->trashed()) {
                    $user->restore();
                }
                $user->update([
                    'patient_id'        => $request->patient_id,
                    'name'              => $request->name,
                    'phone'             => $request->phone,
                    'password'          => bcrypt($request->password),
                    'country_code'      => 'US',
                    'email_verified_at' => now(),
                    'phone_verified_at' => now(),
                ]);
            } else {
                $user = User::create([
                    'patient_id'        => $request->patient_id,
                    'name'              => $request->name,
                    'email'             => $request->email,
                    'phone'             => $request->phone,
                    'password'          => bcrypt($request->password),
                    'country_code'      => 'US',
                    'email_verified_at' => now(),
                    'phone_verified_at' => now(),
                ]);
            }

            // Update funnel assignment with the resolved user id
            $userFunnel->update([
                'user_id' => $user->id,
            ]);

            DB::commit();
            Log::channel('patient_funnel')->info('Patient added to funnel successfully', [
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
                'funnel_id'  => $request->funnel_id,
                'user_id'    => $user->id,
            ]);

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

            Log::channel('patient_funnel')->error('Error adding patient to funnel', [
                'patient_id' => $request->patient_id ?? null,
                'funnel_id'  => $request->funnel_id ?? null,
                'message'    => $e->getMessage(),
                'line'       => $e->getLine(),
                'file'       => $e->getFile(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while adding patient to the funnel.',
            ], 500);
        }
    }

    /**
     * GET /api/get-all-funnel-list
     *
     * Returns active funnels grouped by NPPW, Consent, and Other categories.
     *
     * Request Payload:
     * - None
     *
     * Response:
     * - 200: { status: true, message: string, data: { NPPW: Funnel[], Consent: Funnel[], Other: Funnel[] } }
     * - 500: { status: false, message: string }
     */
    public function getAllFunnelList()
    {
        try {
            Log::channel('patient_funnel')->info('Fetching all active funnels - Start');

            $funnels = Funnel::where('status', 'active')
                ->get(['id', 'name']);

            $groupedFunnels = [
                'NPPW' => [],
                'Consent' => [],
                'Other' => [],
            ];

            foreach ($funnels as $funnel) {

                $name = strtolower($funnel->name);

                if (str_contains($name, 'nppw')) {

                    $groupedFunnels['NPPW'][] = $funnel;

                } elseif (str_contains($name, 'consent')) {

                    $groupedFunnels['Consent'][] = $funnel;

                } else {

                    $groupedFunnels['Other'][] = $funnel;
                }
            }

            Log::channel('patient_funnel')->info('Fetching all active funnels - Success', [
                'total_funnels' => $funnels->count(),
                'nppw_count'    => count($groupedFunnels['NPPW']),
                'consent_count' => count($groupedFunnels['Consent']),
                'other_count'   => count($groupedFunnels['Other']),
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Funnels retrieved successfully.',
                'data'    => $groupedFunnels,
            ], 200);

        } catch (\Throwable $e) {

            Log::channel('patient_funnel')->error('Error fetching all funnels', [
                'error' => $e->getMessage(),
                'line'  => $e->getLine()
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while fetching funnels.',
            ], 500);
        }
    }
}
