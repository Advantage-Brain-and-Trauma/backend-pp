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
use Illuminate\Support\Facades\Http;
use App\Exceptions\SmsDeliveryException;
use App\Mail\AssignFunnelMail;
use App\Models\AhcsPatient;
use App\Models\AhcsCase;
use App\Models\PatientCase;
use App\Services\PatientFormAmdSyncService;
use Illuminate\Validation\Rules\Password;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;
use Carbon\Carbon;

class FunnelApiController extends Controller
{
    /**
     * Consent funnels (name contains "consent") must be resigned after 1 year;
     * other funnel types (health questionnaires, etc.) never expire once completed.
     */
    private function isExpiredConsentAssignment(?Funnel $assignedFunnel, UserFunnel $assignment, array $funnelFormIds): bool
    {
        if (!$assignedFunnel || !str_contains(strtolower($assignedFunnel->name), 'consent')) {
            return false;
        }

        $lastSignedAt = FormSubmission::where('user_funnel_id', $assignment->id)
            ->whereIn('form_id', $funnelFormIds)
            ->where('status', 'completed')
            ->max('created_at');

        return $lastSignedAt && Carbon::parse($lastSignedAt)->lt(now()->subYear());
    }

    /**
     * Normalize a phone number for Twilio SMS delivery.
     * - 10 digits => 1XXXXXXXXXX
     * - 11 digits starting with 1 => 1XXXXXXXXXX
     * - Existing + prefixed value => strip + and keep digits
     */
    private function normalizePhoneForSms(string $phone): string
    {
        $trimmed = trim($phone);
        $digitsOnly = preg_replace('/\D+/', '', $trimmed);

        if (str_starts_with($trimmed, '+')) {
            return preg_replace('/\D+/', '', substr($trimmed, 1));
        }

        if (strlen($digitsOnly) === 10) {
            return '1' . $digitsOnly;
        }

        if (strlen($digitsOnly) === 11 && str_starts_with($digitsOnly, '1')) {
            return $digitsOnly;
        }

        return $digitsOnly;
    }

    /**
     * Canonical form-label => ahcs_patients column map for the patient
     * demographic forms (Patient Intro Form and friends).
     *
     * Used in BOTH directions - prefilling a form from the patient record and
     * writing submitted answers back to it - so the two can never drift apart.
     */
    private function patientFieldMapping(): array
    {
        return [
            'first_name' => 'First Name:',
            'last_name' => 'Last Name:',
            'middle_name' => 'Middle Name:',
            'suffix' => 'Suffix',
            'dob' => 'Date of Birth:',
            'ssn' => 'SSN:',
            'driver_license' => 'Driver’s License Number:',
            'dl_state' => 'DL State',
            'sex' => 'Sex:',
            'address1' => 'Physical Address:',
            'address2' => 'Apartment/Suite Number:',
            'city' => 'City:',
            'state' => 'State:',
            'zip' => 'Zip Code:',
            'mailing_address' => 'Mailing Address (if different from physical address):',
            'mailing_address2' => 'Apt/Suite # (Mailing)',
            'city2' => 'City (Mailing)',
            'state2' => 'State (Mailing)',
            'zip2' => 'Zip (Mailing)',
            // "Phone Number:" is the number patients actually reach us on, so it
            // feeds cell_no (AMD @otherphone); "Mobile" feeds home_ph. Declaration
            // order matters: the first "Mobile" on a form claims home_ph, the second
            // (emergency contact) falls through to emerg_cell below.
            'cell_no' => 'Phone Number:',
            'work_ph' => 'Work Phone:',
            'work_ext' => 'Work Extension:',
            'home_ph' => 'Mobile',
            'wireless_carrier' => 'Wireless Carrier',
            'textmsg_consent' => 'Text Messages?',
            'fax_no' => 'Fax Number:',
            'email' => 'Email:',
            'marital_status' => 'Marital Status:',
            'children' => 'Number of Children:',
            'ethnicity' => 'Ethnicity:',
            'language' => 'Primary Language:',
            'education' => 'Education:',
            'hand_dom' => 'Hand Dominance',
            'emerg_contact' => 'Name',
            'emerg_address' => 'Address',
            'emerg_city' => 'City',
            'emerg_state' => 'State',
            'emerg_zip' => 'Zip',
            'emerg_phone' => 'Phone',
            'emerg_cell' => 'Mobile',
            'emerg_relation' => 'Relationship',
            'allergy' => 'Allergies',
        ];
    }

    /**
     * Spanish equivalents of the labels above, for the bilingual form variants.
     */
    private function patientLabelTranslations(): array
    {
        return [
            'First Name:' => 'El Nombre de Pila:',
            'Middle Name:' => 'El Segundo Nombre:',
            'Last Name:' => 'El Apellido:',
            'Date of Birth:' => 'La Fecha de Nacimiento:',
            'SSN:' => 'El Numero de Seguridad Social:',
            'Sex:' => 'Sex:',
            'Physical Address:' => 'Le Direccion Fisica:',
            'Apartment/Suite Number:' => 'El Apartamento/Numero de Suite:',
            'City:' => 'La Ciudad:',
            'State:' => 'El Estado:',
            'Zip Code:' => 'El Codigo Postal:',
            'Mailing Address (if different from physical address):' => 'Dirección postal (si es diferente de la dirección física):',
            'Phone Number:' => 'numero de telefono:',
            'Work Phone:' => 'telefono del trabajo',
            'Work Extension:' => 'Extensión de trabajo:',
            'Mobile' => 'Número de teléfono móvil:',
            'Fax Number:' => 'El numero de fax:',
            'Email:' => 'El correo electronico:',
            'Driver’s License Number:' => 'Número de licencia de conducir:',
            'Marital Status:' => 'El estado civil:',
            'Number of Children:' => 'Número de niños:',
            'Ethnicity:' => 'la etnia:',
            'Education:' => 'educacion:',
            'Hand Dominance' => 'Dominación de la mano',
            'Primary Language:' => 'Idioma principal:',
            'Name' => 'Nombre',
            'Address' => 'La Direccion',
            'City' => 'Ciudad',
            'Phone' => 'Telephono',
            'Relationship' => 'relacion',
            'Allergies' => 'Alergias',
        ];
    }

    /**
     * Fully normalised label key: smart quotes flattened, whitespace collapsed,
     * trailing colon stripped, lowercased. Used as a fallback so a form writing
     * "Mobile:" still matches a mapping entry declared as "Mobile".
     */
    private function normalizePatientLabel(?string $label): string
    {
        $label = (string) $label;
        $label = str_replace(["\xE2\x80\x99", "\xE2\x80\x98", "\x60"], "'", $label);
        $label = preg_replace('/\s+/', ' ', trim($label));
        $label = rtrim($label, ':');

        return mb_strtolower($label);
    }

    /**
     * Colon-preserving label key. This is what keeps the patient's own "City:"
     * distinct from the emergency contact's "City" - the two collapse into one
     * key as soon as the trailing colon is stripped.
     */
    private function exactPatientLabel(?string $label): string
    {
        $label = (string) $label;
        $label = str_replace(["\xE2\x80\x99", "\xE2\x80\x98", "\x60"], "'", $label);

        return mb_strtolower(preg_replace('/\s+/', ' ', trim($label)));
    }

    /**
     * Build label => [columns...] indexes, keeping EVERY column a label can
     * resolve to, in declaration order, instead of only the first.
     *
     * Several labels are genuinely ambiguous: the patient and their emergency
     * contact are both asked for "Mobile", and "City:"/"City" plus
     * "State:"/"State" collapse together under normalisation. The previous
     * first-wins index made emerg_city, emerg_state and emerg_cell impossible
     * to write, and let the emergency contact's answers overwrite the
     * patient's own city, state and cell_no.
     */
    private function buildPatientLabelIndex(): array
    {
        $exact        = [];
        $normalized   = [];
        $translations = $this->patientLabelTranslations();

        foreach ($this->patientFieldMapping() as $column => $englishLabel) {
            $labels = [$englishLabel];

            if (isset($translations[$englishLabel])) {
                $labels[] = $translations[$englishLabel];
            }

            foreach ($labels as $label) {
                $exact[$this->exactPatientLabel($label)][]          = $column;
                $normalized[$this->normalizePatientLabel($label)][] = $column;
            }
        }

        return ['exact' => $exact, 'normalized' => $normalized];
    }

    /**
     * Resolve one form label to the patient column it should read or write.
     *
     * Exact (colon-preserving) candidates are tried first, so "City:" and
     * "City" separate cleanly no matter what order the form asks for them.
     * Where a label is truly duplicated - "Mobile" appears twice - the first
     * not-yet-claimed column wins, which fills the patient's own column before
     * the emergency contact's, matching the order these forms are laid out in.
     *
     * $claimed is passed by reference and carries claimed columns across one
     * form, so it must be reset per form.
     */
    private function resolvePatientColumn(string $label, array $index, array &$claimed): ?string
    {
        $candidates = $index['exact'][$this->exactPatientLabel($label)]
            ?? $index['normalized'][$this->normalizePatientLabel($label)]
            ?? [];

        foreach ($candidates as $column) {
            if (!isset($claimed[$column])) {
                $claimed[$column] = true;

                return $column;
            }
        }

        return null;
    }

    /**
     * GET /api/get-patient-funnels
     *
     * Returns funnels assigned to the authenticated user.
     *
     * Request Payload:
     * - case_id (optional, integer)
     *
     * Response:
     * - 200: { status: true, message: string, data: [{ id, funnel_name, submission_status, pending_count }] }
     * - 500: { status: false, message: string }
     */
    

    public function getPatientFunnels(Request $request)
    {
        try {

            $caseId     = $request->input('case_id');
            $patientIds = $request->user()->getActivePatientIds();

            if (empty($caseId)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Case Id is required.',
                ], 422);
            }

            $isValidCaseForPatient = AhcsCase::where('id', $caseId)
                ->whereIn('patient_id', $patientIds)
                ->exists();

            if (!$isValidCaseForPatient) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid Case Id for this patient.',
                ], 422);
            }

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

            $userFunnelRows = $userFunnelsQuery
                ->get(['id', 'funnel_id']);

            $userFunnels = $userFunnelRows->pluck('funnel_id');
            $userFunnelIdByFunnel = $userFunnelRows
                ->pluck('id', 'funnel_id');

            Log::channel('patient_funnel')->info('User funnel IDs fetched', [
                'funnel_ids' => $userFunnels
            ]);

            $funnels = Funnel::whereIn('id', $userFunnels)
                ->where('status', 'active')
                ->get(['id', 'name', 'form_ids']);

            $funnels->transform(function ($funnel) use ($request, $userFunnelIdByFunnel) {

                $formIds = is_array($funnel->form_ids)
                    ? $funnel->form_ids
                    : json_decode($funnel->form_ids ?? '[]', true);

                $formIds = is_array($formIds) ? $formIds : [];

                $totalForms = count($formIds);

                $userFunnelId = $userFunnelIdByFunnel->get($funnel->id);

                $submittedForms = FormSubmission::where('user_id', $request->user()->id)
                    ->where('funnel_id', $funnel->id)
                    ->where('user_funnel_id', $userFunnelId)
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
     * GET /api/check-funnel-form-completion
     *
     * Request Payload:
     * - patient_id, case_id
     *
     * Response:
     * - 200: { status: true, message: string, data: [{ id, funnel_name, form_submission_status }] }
     * - 500: { status: false, message: string }
     */


    public function checkFunnelFormCompletion(Request $request)
    {
        try {

            $patientId = $request->input('patient_id');
            $caseId    = $request->input('case_id');

            if (empty($patientId)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Patient Id is required.',
                ], 422);
            }

            if (empty($caseId)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Case Id is required.',
                ], 422);
            }

            // Validate that case belongs to patient
            $isValidCase = AhcsCase::where('id', $caseId)
                ->where('patient_id', $patientId)
                ->exists();

            if (!$isValidCase) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid Case Id for this patient.',
                ], 422);
            }

            // Fetch portal user using patient_id
            $user = User::where('patient_id', 'LIKE', '%'.$patientId.'%')->first();

            if (!$user) {
                return response()->json([
                    'status'  => true,
                    'data'    => [],
                ], 404);
            }

            Log::channel('patient_funnel')->info('Fetching patient funnels - Start', [
                'user_id'    => $user->id,
                'patient_id' => $patientId,
                'case_id'    => $caseId,
            ]);

            $userFunnelsQuery = UserFunnel::where('user_id', $user->id)
                ->whereHas('patientCase', function ($q) use ($caseId) {
                    $q->where('case_id', $caseId);
                });

            $userFunnelRows = $userFunnelsQuery->get(['id', 'funnel_id']);

            $userFunnels = $userFunnelRows->pluck('funnel_id');

            $userFunnelIdByFunnel = $userFunnelRows->pluck('id', 'funnel_id');

            $funnels = Funnel::whereIn('id', $userFunnels)
                ->where('status', 'active')
                ->whereRaw('LOWER(name) LIKE ?', ['%nppw%'])
                ->limit(1)
                ->get(['id', 'name', 'form_ids']);

            $funnels->transform(function ($funnel) use ($user, $userFunnelIdByFunnel) {

                $formIds = is_array($funnel->form_ids)
                    ? $funnel->form_ids
                    : json_decode($funnel->form_ids ?? '[]', true);

                $formIds = is_array($formIds) ? $formIds : [];

                $userFunnelId = $userFunnelIdByFunnel->get($funnel->id);

                $submittedForms = FormSubmission::where('user_id', $user->id)
                    ->where('funnel_id', $funnel->id)
                    ->where('user_funnel_id', $userFunnelId)
                    ->whereIn('form_id', $formIds)
                    ->where('status', 'completed')
                    ->distinct('form_id')
                    ->count('form_id');

                return [
                    'id'                       => $funnel->id,
                    'funnel_name'              => $funnel->name,
                    'form_submission_status'   => $submittedForms > 0 ? 'completed' : 'pending',
                ];
            });

            Log::channel('patient_funnel')->info('Fetching patient funnels - Success', [
                'user_id'       => $user->id,
                'patient_id'    => $patientId,
                'total_funnels' => $funnels->count(),
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Funnels retrieved successfully.',
                'data'    => $funnels,
            ], 200);

        } catch (\Throwable $e) {

            Log::channel('patient_funnel')->error('Error fetching patient funnels', [
                'patient_id' => $patientId ?? null,
                'case_id'    => $caseId ?? null,
                'message'    => $e->getMessage(),
                'line'       => $e->getLine(),
                'file'       => $e->getFile(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Error fetching patient funnels.',
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
     * - case_id (optional, integer)
     *
     * Response:
     * - 200: { status: true, message: string, data: { id, funnel_name, forms: [{ id, name, description, submission_status, fields }] } }
     * - 404: { status: false, message: string } (patient/funnel/case mapping not found)
     * - 500: { status: false, message: string }
     */

    public function getPatientFunnelSubmissionDetails(Request $request, $funnelId)
    {
        try {
            $userId     = auth()->id();
            $caseId     = $request->input('case_id');
            $patientIds = auth()->user()->getActivePatientIds();

            if (empty($caseId)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Case Id is required.',
                ], 422);
            }

            // Validate the case belongs to one of this user's patient IDs and
            // resolve the exact patient_id associated with the case in one query.
            $caseRecord = AhcsCase::where('id', $caseId)
                ->whereIn('patient_id', $patientIds)
                ->first(['patient_id']);

            if (!$caseRecord) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid Case Id for this patient.',
                ], 422);
            }

            $patientId = $caseRecord->patient_id;

            $labelIndex = $this->buildPatientLabelIndex();
            $patient = AhcsPatient::where('id', $patientId)->first();

            if (!$patient) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Patient not found',
                ], 404);
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
                ->where('user_funnel_id', $userFunnel->id)
                ->get(['form_id', 'status']);

            $forms = $formDetails->map(function ($form) use ($submissions, $patient, $labelIndex) {
                $submission = $submissions->where('form_id', $form->id)->first();

                $fields = is_array($form->fields)
                    ? $form->fields
                    : json_decode($form->fields ?? '[]', true);

                // Claimed columns are tracked across the whole form, in field order, so a
                // second field labelled "Mobile" resolves to emerg_cell rather than
                // repeating the patient's own cell_no.
                $claimedColumns = [];

                $onlyFields = collect($fields['rows'] ?? [])
                    ->flatMap(function ($row) use ($patient, $labelIndex, &$claimedColumns) {
                        return collect($row['cols'] ?? [])
                            ->flatMap(function ($col) use ($patient, $labelIndex, &$claimedColumns) {
                                return collect($col['fields'] ?? [])->map(function ($field) use ($patient, $labelIndex, &$claimedColumns) {

                                    $label  = trim($field['label'] ?? '');
                                    $column = $label === ''
                                        ? null
                                        : $this->resolvePatientColumn($label, $labelIndex, $claimedColumns);
                                    $value  = $column !== null ? ($patient->{$column} ?? null) : null;

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

            $userId     = auth()->id();
            $patientIds = auth()->user()->getActivePatientIds();
            $caseId     = $request->input('case_id');

            if (empty($caseId)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Case Id is required.',
                ], 422);
            }

            // Validate case and resolve the exact patient_id for this case (needed
            // later for AMD sync which requires a single integer).
            $caseRecord = AhcsCase::where('id', $caseId)
                ->whereIn('patient_id', $patientIds)
                ->first(['patient_id']);

            $patientId = $caseRecord?->patient_id;

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

            if (!$caseRecord) {
                Log::channel('patient_form')->warning('Invalid patient or case', [
                    'user_id'    => $userId,
                    'patient_ids'=> $patientIds,
                    'case_id'    => $caseId,
                ]);
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid patient or case',
                ], 403);
            }

            $userFunnel = UserFunnel::where('user_id', $userId)
                ->where('funnel_id', $request->funnel_id)
                ->whereHas('patientCase', function ($q) use ($caseId, $patientId) {
                    $q->where('case_id', $caseId)
                        ->where('patient_id', $patientId);
                })
                ->first();

            if (!$userFunnel) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Funnel not assigned for this patient case.',
                ], 404);
            }

            $alreadySubmitted = FormSubmission::where('user_id', $userId)
                ->where('form_id', $formId)
                ->where('funnel_id', $request->funnel_id)
                ->where('user_funnel_id', $userFunnel->id)
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
            $patientUpdateData = [];
            $existingPatientArray = [];
            $amdSyncResult = null;

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

                $labelIndex     = $this->buildPatientLabelIndex();
                $claimedColumns = [];

                foreach ($fieldsInput as $field) {
                    if (!is_array($field)) {
                        continue;
                    }

                    $label = (string) ($field['label'] ?? $field['lable'] ?? '');

                    if (trim($label) === '') {
                        continue;
                    }

                    $column = $this->resolvePatientColumn($label, $labelIndex, $claimedColumns);

                    if ($column !== null) {
                        $patientUpdateData[$column] = $field['value'] ?? null;
                    }
                }
                $existingPatient = AhcsPatient::find($patientId);
                $existingPatientArray = $existingPatient ? $existingPatient->toArray() : [];

                $patientNameParts = array_filter([
                    trim((string) ($patientUpdateData['first_name'] ?? $existingPatient?->first_name ?? '')),
                    trim((string) ($patientUpdateData['middle_name'] ?? $existingPatient?->middle_name ?? '')),
                    trim((string) ($patientUpdateData['last_name'] ?? $existingPatient?->last_name ?? '')),
                ], fn ($part) => $part !== '');

                if (!empty($patientNameParts)) {
                    $patientUpdateData['patient_name'] = implode(' ', $patientNameParts);
                }

                if (!empty($patientUpdateData)) {
                    Log::channel('patient_form')->info('Updating patient data', [
                        'patient_id' => $patientId,
                        'old_data' => optional($existingPatient)->toArray(),
                        'data'       => $patientUpdateData,
                    ]);
                    AhcsPatient::where('id', $patientId)->update($patientUpdateData);
                }

                $userUpdateData = [];
                $nameParts = array_filter([
                    trim((string) ($patientUpdateData['first_name'] ?? '')),
                    trim((string) ($patientUpdateData['middle_name'] ?? '')),
                    trim((string) ($patientUpdateData['last_name'] ?? '')),
                ], fn ($part) => $part !== '');

                if (!empty($nameParts)) {
                    $userUpdateData['name'] = implode(' ', $nameParts);
                }

                if (!empty($patientUpdateData['email'])) {
                    $userUpdateData['email'] = $patientUpdateData['email'];
                }

                $phoneValue = $patientUpdateData['cell_no']
                    ?? $patientUpdateData['home_ph']
                    ?? $patientUpdateData['work_ph']
                    ?? null;

                if (!empty($phoneValue)) {
                    $userUpdateData['phone'] = $phoneValue;
                }

                if (!empty($userUpdateData)) {
                    Log::channel('patient_form')->info('Updating user data from patient form', [
                        'user_id' => $userId,
                        'data'    => $userUpdateData,
                    ]);

                    User::where('id', $userId)->update($userUpdateData);
                }

                Log::channel('patient_form')->info('Creating form submission', [
                    'user_id'   => $userId,
                    'form_id'   => $formId,
                    'funnel_id' => $request->input('funnel_id'),
                    'data'      => $formData,
                    'status'    => 'completed',
                ]);

                $submission = FormSubmission::create([
                    'user_id'    => $userId,
                    'form_id'    => $formId,
                    'funnel_id'  => $request->input('funnel_id'),
                    'user_funnel_id' => $userFunnel->id,
                    'data'       => $formData,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'status'     => 'completed',
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
                $amdSyncService = app(PatientFormAmdSyncService::class);
                $amdSyncResult = $amdSyncService->syncDemographics(
                    (int) $patientId,
                    (int) $caseId,
                    $patientUpdateData,
                    $existingPatientArray
                );

                Log::channel('patient_form')->info('AMD sync attempted after patient form submit', [
                    'patient_id' => $patientId,
                    'case_id' => $caseId,
                    'result' => $amdSyncResult,
                ]);
            } catch (\Throwable $amdError) {
                $amdSyncResult = [
                    'status' => 'failed',
                    'message' => 'Something went wrong',
                ];

                Log::channel('patient_form')->error('AMD sync failed after patient form submit', [
                    'patient_id' => $patientId,
                    'case_id' => $caseId,
                    'error' => $amdError->getMessage(),
                ]);
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
                    'amd_sync'      => $amdSyncResult,
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

            // Check for an existing user by email OR patient_id membership
            // (handles both old plain-int and new JSON-array storage formats).
            $user = User::where('email', $request->email)
                ->orWhere(function ($q) use ($request) {
                    $pid = (int) $request->patient_id;
                    $q->whereJsonContains('patient_id', $pid)
                      ->orWhere('patient_id', $pid);
                })
                ->first();

            $userId = $user?->id;
            $flag   = $user ? 'user_exists' : 'no_user';
            $patientName = $patient->patient_name
                ?? $user?->name
                ?? 'Patient';

            // If user already exists, append the patient_id to their array without
            // overwriting any previously stored patient IDs.
            if ($user) {
                $user->appendPatientId((int) $request->patient_id);
            }

            // Create patient case if not exists
            $patientCase = PatientCase::firstOrCreate([
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
            ]);

            // Guard: reject if an ACTIVE (non-deleted) assignment of THIS SAME funnel
            // already exists for this patient + case. Multiple different funnels can
            // be assigned to the same patient case — only re-assigning the same
            // funnel is blocked. A previously DELETED assignment is intentionally
            // ignored here — re-assigning after deletion must always start fresh.

            $existingActiveAssignment = UserFunnel::where('patient_id', $request->patient_id)
                ->where('patient_case_id', $patientCase->id)
                ->where('funnel_id', $request->funnel_id)
                ->first();

            if ($existingActiveAssignment) {
                $assignedFunnel = Funnel::find($existingActiveAssignment->funnel_id);
                $funnelFormIds  = $assignedFunnel
                    ? (is_array($assignedFunnel->form_ids)
                        ? $assignedFunnel->form_ids
                        : json_decode($assignedFunnel->form_ids ?? '[]', true))
                    : [];
                $funnelFormIds = is_array($funnelFormIds) ? $funnelFormIds : [];

                $completedCount = count($funnelFormIds) > 0
                    ? FormSubmission::where('user_funnel_id', $existingActiveAssignment->id)
                        ->whereIn('form_id', $funnelFormIds)
                        ->where('status', 'completed')
                        ->distinct('form_id')
                        ->count('form_id')
                    : 0;

                if (count($funnelFormIds) > 0 && $completedCount >= count($funnelFormIds)) {
                    if ($this->isExpiredConsentAssignment($assignedFunnel, $existingActiveAssignment, $funnelFormIds)) {
                        // Consent forms must be resigned after 1 year (health questionnaires and
                        // other non-consent funnels never expire). Treat this like there was no
                        // active assignment so a fresh one gets created and sent below.
                        Log::channel('patient_funnel')->info('Consent funnel expired (signed over 1 year ago); reassigning.', [
                            'patient_id' => $request->patient_id,
                            'case_id'    => $request->case_id,
                            'funnel_id'  => $existingActiveAssignment->funnel_id,
                        ]);
                        $existingActiveAssignment = null;
                    } else {
                        DB::rollBack();
                        Log::channel('patient_funnel')->info('Funnel already completed for this patient case.', [
                            'patient_id' => $request->patient_id,
                            'case_id'    => $request->case_id,
                            'funnel_id'  => $existingActiveAssignment->funnel_id,
                            'user_id'    => $existingActiveAssignment->user_id,
                        ]);
                        return response()->json([
                            'status'  => true,
                            'message' => 'Funnel is already completed for this patient case.',
                            'funnel_completed' => true,
                        ], 200);
                    }
                }

                if ($existingActiveAssignment) {
                    Log::channel('patient_funnel')->warning('Active funnel assignment already exists for this patient case. Sending reminder.', [
                        'patient_id' => $request->patient_id,
                        'case_id'    => $request->case_id,
                        'funnel_id'  => $existingActiveAssignment->funnel_id,
                        'user_id'    => $existingActiveAssignment->user_id,
                    ]);
                }
            }

            // Always create a brand-new UserFunnel record.
            //
            // WHY: Restoring a soft-deleted record reuses the same primary key
            // (user_funnel_id). All FormSubmissions previously linked to that ID
            // would instantly re-appear, making the patient look like they already
            // completed everything even though the assignment was deleted and
            // re-created intentionally. A new record = new ID = clean slate.
            if(!$existingActiveAssignment) {
                UserFunnel::create([
                    'user_id'         => $userId,
                    'patient_id'      => $request->patient_id,
                    'funnel_id'       => $request->funnel_id,
                    'patient_case_id' => $patientCase->id,
                    'assigned_via'    => 'email',
                    'assigned_at'     => now(),
                    'email'           => $request->email,
                ]);
            }

            $funnelMail = new AssignFunnelMail(
                $request->patient_id,
                $request->case_id,
                $request->funnel_id,
                $request->funnel_name,
                $patientName,
                $request->email,
                $request->phone,
                $flag,
                'email',
                false
            );

            // Send email
            Mail::to($request->email)->send($funnelMail);
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
                'url'     => $funnelMail->funnelUrl,
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

    public function checkAssignFunnel(Request $request)
    {
        try {
            Log::channel('patient_funnel')->info('Assign funnel request received', [
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
            ]);

            $validator = Validator::make($request->all(), [
                'patient_id'  => 'required|integer|exists:ahcs.ahcs_patients,id',
                'case_id'     => 'required|integer|exists:ahcs.ahcs_cases,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // Create patient case if not exists
            $patientCase = PatientCase::firstOrCreate([
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
            ]);

            $existingActiveAssignment = UserFunnel::where('patient_id', $request->patient_id)
                ->where('patient_case_id', $patientCase->id)
                ->first();

            if ($existingActiveAssignment) {

                $flag = false;
                $funnelName = Funnel::where('id', $existingActiveAssignment->funnel_id ?? null)->value('name');
                if(isset($existingActiveAssignment->user_id) and !is_null($existingActiveAssignment->user_id)){
                    $flag = true;
                }
                return response()->json([
                    'status'  => true,
                    'funnel_id' => $existingActiveAssignment->funnel_id,
                    'funnel_name' => $funnelName,
                    'assign_funnel' => $flag,
                ]);
            }

            return response()->json([
                'status'  => true,
                'assign_funnel' => false,
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status'  => false,
                'message' => 'An error occurred while checking funnel assignment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/check-multiple-assign-funnel
     *
     * Same as checkAssignFunnel(), but returns every funnel currently assigned
     * to the patient/case instead of only the first one.
     *
     * Request Payload:
     * - patient_id (required, int, exists in ahcs.ahcs_patients)
     * - case_id (required, int, exists in ahcs.ahcs_cases)
     * 
     *
     * Response:
     * - 200: { status: true, assign_funnel: bool, funnels: [{ funnel_id, funnel_name, assign_funnel }] }
     * - 422: { status: false, message: string, errors: object }
     * - 500: { status: false, message: string }
     */
    public function checkMultipleAssignFunnel(Request $request)
    {
        try {
            Log::channel('patient_funnel')->info('Check multiple assign funnel request received', [
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
            ]);

            $validator = Validator::make($request->all(), [
                'patient_id'  => 'required|integer|exists:ahcs.ahcs_patients,id',
                'case_id'     => 'required|integer|exists:ahcs.ahcs_cases,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // Create patient case if not exists
            $patientCase = PatientCase::firstOrCreate([
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
            ]);

            $existingActiveAssignments = UserFunnel::where('patient_id', $request->patient_id)
                ->where('patient_case_id', $patientCase->id)
                ->get();

            if ($existingActiveAssignments->isEmpty()) {
                return response()->json([
                    'status'  => true,
                    'assign_funnel' => false,
                    'funnels' => [],
                ]);
            }

            $funnelIds = $existingActiveAssignments->pluck('funnel_id')->unique()->values();
            $funnelNamesById = Funnel::whereIn('id', $funnelIds)->pluck('name', 'id');

            $funnels = $existingActiveAssignments->map(function ($assignment) use ($funnelNamesById) {
                return [
                    'funnel_id'     => $assignment->funnel_id,
                    'funnel_name'   => $funnelNamesById->get($assignment->funnel_id),
                    'assign_funnel' => isset($assignment->user_id) && !is_null($assignment->user_id),
                ];
            })->values();

            return response()->json([
                'status'  => true,
                'assign_funnel' => true,
                'funnels' => $funnels,
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status'  => false,
                'message' => 'An error occurred while checking funnel assignment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/assign-funnel-sms
     *
     * Assigns a funnel to a patient/case and sends the assignment SMS.
     *
     * Request Payload:
     * - patient_id (required, int, exists in ahcs.ahcs_patients)
     * - case_id (required, int, exists in ahcs.ahcs_cases)
     * - funnel_id (required, int, exists in funnels)
     * - funnel_name (required, string)
     * - phone (required, string)
     * - email (optional, valid email) — stored in user_funnels and used for user lookup
     *
     * Response:
     * - 200: { status: true, message: string }
     * - 422: { status: false, message: string, errors: string } (validation failed)
     * - 422: { status: false, message: string, error_code: int|null } (Twilio rejected
     *        the send; message is Twilio's own wording, e.g. "Invalid 'To' Phone Number: …")
     * - 500: { status: false, message: string }
     */
    public function assignFunnelSms(Request $request)
    {
        try {
            Log::channel('patient_funnel')->info('Assign funnel SMS request received', [
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
                'funnel_id'  => $request->funnel_id,
            ]);

            $validator = Validator::make($request->all(), [
                'patient_id'  => 'required|integer|exists:ahcs.ahcs_patients,id',
                'case_id'     => 'required|integer|exists:ahcs.ahcs_cases,id',
                'funnel_id'   => 'required|integer|exists:funnels,id',
                'funnel_name' => 'required|string|max:255',
                'phone'       => 'required|string|max:20',
                'email'       => 'nullable|email|max:255',
            ]);

            if ($validator->fails()) {
                Log::channel('patient_funnel')->warning('Assign funnel SMS validation failed', [
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
            $normalizedPhone = $this->normalizePhoneForSms((string) $request->phone);
            $normalizedDigits = preg_replace('/\D+/', '', $normalizedPhone);
            if (strlen($normalizedDigits) < 11) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => 'Please provide a valid phone number.',
                ], 422);
            }

            $patient = AhcsPatient::find($request->patient_id);

            // Check for an existing user by email OR patient_id membership
            // (handles both old plain-int and new JSON-array storage formats).
            $user = $request->filled('email')
                ? User::where('email', $request->email)
                      ->orWhere(function ($q) use ($request) {
                          $pid = (int) $request->patient_id;
                          $q->whereJsonContains('patient_id', $pid)
                            ->orWhere('patient_id', $pid);
                      })
                      ->first()
                : User::hasPatientId((int) $request->patient_id)->first();

            $userId = $user?->id;
            $flag   = $user ? 'user_exists' : 'no_user';
            $patientName = $patient->patient_name
                ?? $user?->name
                ?? 'Patient';

            // If user already exists, append the patient_id to their array without
            // overwriting any previously stored patient IDs.
            if ($user) {
                $user->appendPatientId((int) $request->patient_id);
            }

            $patientCase = PatientCase::firstOrCreate([
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
            ]);

            // Guard: reject if an ACTIVE (non-deleted) assignment of THIS SAME funnel
            // already exists for this patient + case. Multiple different funnels can
            // be assigned to the same patient case — only re-assigning the same
            // funnel is blocked. A previously DELETED assignment is intentionally
            // ignored here — re-assigning after deletion must always start fresh.

            $existingActiveAssignment = UserFunnel::where('patient_id', $request->patient_id)
                ->where('patient_case_id', $patientCase->id)
                ->where('funnel_id', $request->funnel_id)
                ->first();

            if ($existingActiveAssignment) {
                $assignedFunnel = Funnel::find($existingActiveAssignment->funnel_id);
                $funnelFormIds  = $assignedFunnel
                    ? (is_array($assignedFunnel->form_ids)
                        ? $assignedFunnel->form_ids
                        : json_decode($assignedFunnel->form_ids ?? '[]', true))
                    : [];
                $funnelFormIds = is_array($funnelFormIds) ? $funnelFormIds : [];

                $completedCount = count($funnelFormIds) > 0
                    ? FormSubmission::where('user_funnel_id', $existingActiveAssignment->id)
                        ->whereIn('form_id', $funnelFormIds)
                        ->where('status', 'completed')
                        ->distinct('form_id')
                        ->count('form_id')
                    : 0;

                if (count($funnelFormIds) > 0 && $completedCount >= count($funnelFormIds)) {
                    if ($this->isExpiredConsentAssignment($assignedFunnel, $existingActiveAssignment, $funnelFormIds)) {
                        // Consent forms must be resigned after 1 year (health questionnaires and
                        // other non-consent funnels never expire). Treat this like there was no
                        // active assignment so a fresh one gets created and sent below.
                        Log::channel('patient_funnel')->info('Consent funnel expired (signed over 1 year ago); reassigning.', [
                            'patient_id' => $request->patient_id,
                            'case_id'    => $request->case_id,
                            'funnel_id'  => $existingActiveAssignment->funnel_id,
                        ]);
                        $existingActiveAssignment = null;
                    } else {
                        DB::rollBack();
                        Log::channel('patient_funnel')->info('Funnel already completed for this patient case.', [
                            'patient_id' => $request->patient_id,
                            'case_id'    => $request->case_id,
                            'funnel_id'  => $existingActiveAssignment->funnel_id,
                            'user_id'    => $existingActiveAssignment->user_id,
                        ]);
                        return response()->json([
                            'status'  => true,
                            'message' => 'Funnel is already completed for this patient case.',
                            'funnel_completed' => true,
                        ], 200);
                    }
                }

                if ($existingActiveAssignment) {
                    Log::channel('patient_funnel')->warning('Active funnel assignment already exists for this patient case. Sending reminder.', [
                        'patient_id' => $request->patient_id,
                        'case_id'    => $request->case_id,
                        'funnel_id'  => $existingActiveAssignment->funnel_id,
                        'user_id'    => $existingActiveAssignment->user_id,
                    ]);
                }
            }

            // Always create a brand-new UserFunnel record.
            //
            // WHY: Restoring a soft-deleted record reuses the same primary key
            // (user_funnel_id). All FormSubmissions previously linked to that ID
            // would instantly re-appear, making the patient look like they already
            // completed everything even though the assignment was deleted and
            // re-created intentionally. A new record = new ID = clean slate.
            if(!$existingActiveAssignment) {
                UserFunnel::create([
                    'user_id'         => $userId,
                    'patient_id'      => $request->patient_id,
                    'funnel_id'       => $request->funnel_id,
                    'patient_case_id' => $patientCase->id,
                    'assigned_via'    => 'sms',
                    'assigned_at'     => now(),
                    'email'           => $request->email ?: null,
                    'phone_no'        => $normalizedPhone,
                ]);
            } else {
                // Re-sending an existing assignment over SMS: the row may have been
                // created by an email assignment, leaving phone_no NULL. The link we
                // are about to send declares source=sms, and registration then filters
                // on phone_no — so the row must carry the number or the patient gets
                // "User funnel assignment not found" and can never create an account.
                $existingActiveAssignment->forceFill([
                    'phone_no'     => $normalizedPhone,
                    'assigned_via' => 'sms',
                    'email'        => $existingActiveAssignment->email ?: ($request->email ?: null),
                ])->save();
            }

            // Prefer any email we already hold for this patient so the registration
            // form can prefill it. assignFunnelSms is often called before a user
            // account exists, in which case the assignment row is the only source.
            $linkEmail = (string) (
                $user?->email
                ?: $existingActiveAssignment?->email
                ?: $request->email
                ?: ''
            );

            $funnelUrl = (new AssignFunnelMail(
                (string) $request->patient_id,
                (string) $request->case_id,
                (string) $request->funnel_id,
                (string) $request->funnel_name,
                (string) $patientName,
                $linkEmail,
                (string) $normalizedPhone,
                (string) $flag,
                'sms',
                false
            ))->funnelUrl;

            $twilioSid = config('services.twilio.sid');
            $twilioToken = config('services.twilio.token');
            $twilioFrom = config('services.twilio.from');

            if (empty($twilioSid) || empty($twilioToken) || empty($twilioFrom)) {
                throw new RuntimeException('Twilio SMS configuration is missing.');
            }

            $smsBody = "Hello, {$patientName}.\n"
                . "You have received a new funnel form link for: {$request->funnel_name}. Please use the link below to access and complete the form.\n"
                . "Click here to open your funnel form: {$funnelUrl}\n"
                . "If you have any questions, feel free to contact support.\n\n"
                . "Best Regards,\n"
                . "MedHiWa Team";

            $smsResponse = Http::withBasicAuth($twilioSid, $twilioToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json", [
                    'From' => $twilioFrom,
                    'To'   => $normalizedPhone,
                    'Body' => $smsBody,
                ]);

            if ($smsResponse->failed()) {
                throw SmsDeliveryException::fromTwilioResponse($smsResponse);
            }

            DB::commit();

            Log::channel('patient_funnel')->info('Funnel assigned and SMS sent successfully', [
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
                'funnel_id'  => $request->funnel_id,
                'user_id'    => $userId,
                'flag'       => $flag,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Funnel assigned and SMS sent successfully.',
                'url'     => $funnelUrl,
            ], 200);

        } catch (SmsDeliveryException $e) {
            // Twilio rejected the request itself (bad number, unreachable carrier, …).
            // That is correctable by whoever is sending the form, so return Twilio's
            // own wording rather than a generic failure.
            DB::rollBack();

            Log::channel('patient_funnel')->error('SMS delivery rejected while assigning funnel', [
                'patient_id'      => $request->patient_id ?? null,
                'case_id'         => $request->case_id ?? null,
                'funnel_id'       => $request->funnel_id ?? null,
                'twilio_code'     => $e->twilioCode,
                'twilio_response' => $e->rawBody,
            ]);

            return response()->json([
                'status'     => false,
                'message'    => $e->getMessage(),
                'error_code' => $e->twilioCode,
            ], 422);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::channel('patient_funnel')->error('Error assigning funnel with SMS', [
                'patient_id' => $request->patient_id ?? null,
                'case_id'    => $request->case_id ?? null,
                'funnel_id'  => $request->funnel_id ?? null,
                'message'    => $e->getMessage(),
                'line'       => $e->getLine(),
                'file'       => $e->getFile(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while assigning the funnel via SMS.',
            ], 500);
        }
    }

    /**
     * POST /api/multiple-assign-funnel
     *
     * Assigns multiple funnels to a patient/case in a single request and sends
     * one assignment email per funnel. Per-funnel behavior (existing-assignment
     * guard, consent expiry, UserFunnel creation) is identical to assignFunnel().
     *
     * Request Payload:
     * - patient_id (required, int, exists in ahcs.ahcs_patients)
     * - case_id (required, int, exists in ahcs.ahcs_cases)
     * - email (required, valid email)
     * - phone (nullable, string, max:20)
     * - funnels (required, array, min:1)
     *   - funnels.*.funnel_id (required, int, exists in funnels)
     *   - funnels.*.funnel_name (required, string, max:255)
     *
     * Example payload:
     * {
     *   "patient_id": 123,
     *   "case_id": 456,
     *   "email": "patient@example.com",
     *   "phone": "5551234567",
     *   "funnels": [
     *     { "funnel_id": 1, "funnel_name": "NPPW Consent" },
     *     { "funnel_id": 2, "funnel_name": "Health Questionnaire" }
     *   ]
     * }
     *
     * Response:
     * - 200: { status: true, message: string, results: [{ funnel_id, funnel_name, status, message, url? }] }
     * - 422: { status: false, message: string, errors: string }
     * - 500: { status: false, message: string }
     */
    public function multipleAssignFunnel(Request $request)
    {
        try {
            Log::channel('patient_funnel')->info('Multiple assign funnel request received', [
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
                'funnels'    => $request->funnels,
            ]);

            $validator = Validator::make($request->all(), [
                'patient_id'            => 'required|integer|exists:ahcs.ahcs_patients,id',
                'case_id'               => 'required|integer|exists:ahcs.ahcs_cases,id',
                'email'                 => 'required|email',
                'phone'                 => 'nullable|string|max:20',
                'funnels'               => 'required|array|min:1',
                'funnels.*.funnel_id'   => 'required|integer|exists:funnels,id',
                'funnels.*.funnel_name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                Log::channel('patient_funnel')->warning('Multiple assign funnel validation failed', [
                    'patient_id' => $request->patient_id,
                    'case_id'    => $request->case_id,
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

            // Check for an existing user by email OR patient_id membership
            // (handles both old plain-int and new JSON-array storage formats).
            $user = User::where('email', $request->email)
                ->orWhere(function ($q) use ($request) {
                    $pid = (int) $request->patient_id;
                    $q->whereJsonContains('patient_id', $pid)
                      ->orWhere('patient_id', $pid);
                })
                ->first();

            $userId = $user?->id;
            $flag   = $user ? 'user_exists' : 'no_user';
            $patientName = $patient->patient_name
                ?? $user?->name
                ?? 'Patient';

            // If user already exists, append the patient_id to their array without
            // overwriting any previously stored patient IDs.
            if ($user) {
                $user->appendPatientId((int) $request->patient_id);
            }

            // Create patient case if not exists
            $patientCase = PatientCase::firstOrCreate([
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
            ]);

            $results     = [];
            $primaryMail = null;

            foreach ($request->funnels as $funnelInput) {
                $funnelId   = (int) $funnelInput['funnel_id'];
                $funnelName = $funnelInput['funnel_name'];

                // Guard: reject if an ACTIVE (non-deleted) assignment of THIS SAME funnel
                // already exists for this patient + case. Multiple different funnels can
                // be assigned to the same patient case — only re-assigning the same
                // funnel is blocked. A previously DELETED assignment is intentionally
                // ignored here — re-assigning after deletion must always start fresh.

                $existingActiveAssignment = UserFunnel::where('patient_id', $request->patient_id)
                    ->where('patient_case_id', $patientCase->id)
                    ->where('funnel_id', $funnelId)
                    ->first();

                if ($existingActiveAssignment) {
                    $assignedFunnel = Funnel::find($existingActiveAssignment->funnel_id);
                    $funnelFormIds  = $assignedFunnel
                        ? (is_array($assignedFunnel->form_ids)
                            ? $assignedFunnel->form_ids
                            : json_decode($assignedFunnel->form_ids ?? '[]', true))
                        : [];
                    $funnelFormIds = is_array($funnelFormIds) ? $funnelFormIds : [];

                    $completedCount = count($funnelFormIds) > 0
                        ? FormSubmission::where('user_funnel_id', $existingActiveAssignment->id)
                            ->whereIn('form_id', $funnelFormIds)
                            ->where('status', 'completed')
                            ->distinct('form_id')
                            ->count('form_id')
                        : 0;

                    if (count($funnelFormIds) > 0 && $completedCount >= count($funnelFormIds)) {
                        if ($this->isExpiredConsentAssignment($assignedFunnel, $existingActiveAssignment, $funnelFormIds)) {
                            // Consent forms must be resigned after 1 year (health questionnaires and
                            // other non-consent funnels never expire). Treat this like there was no
                            // active assignment so a fresh one gets created and sent below.
                            Log::channel('patient_funnel')->info('Consent funnel expired (signed over 1 year ago); reassigning.', [
                                'patient_id' => $request->patient_id,
                                'case_id'    => $request->case_id,
                                'funnel_id'  => $funnelId,
                            ]);
                            $existingActiveAssignment = null;
                        } else {
                            Log::channel('patient_funnel')->info('Funnel already completed for this patient case.', [
                                'patient_id' => $request->patient_id,
                                'case_id'    => $request->case_id,
                                'funnel_id'  => $funnelId,
                                'user_id'    => $existingActiveAssignment->user_id,
                            ]);
                            $results[] = [
                                'funnel_id'   => $funnelId,
                                'funnel_name' => $funnelName,
                                'status'      => 'already_completed',
                                'message'     => 'Funnel is already completed for this patient case.',
                            ];
                            continue;
                        }
                    }

                    if ($existingActiveAssignment) {
                        Log::channel('patient_funnel')->warning('Active funnel assignment already exists for this patient case. Sending reminder.', [
                            'patient_id' => $request->patient_id,
                            'case_id'    => $request->case_id,
                            'funnel_id'  => $funnelId,
                            'user_id'    => $existingActiveAssignment->user_id,
                        ]);
                    }
                }

                // Always create a brand-new UserFunnel record.
                //
                // WHY: Restoring a soft-deleted record reuses the same primary key
                // (user_funnel_id). All FormSubmissions previously linked to that ID
                // would instantly re-appear, making the patient look like they already
                // completed everything even though the assignment was deleted and
                // re-created intentionally. A new record = new ID = clean slate.
                if (!$existingActiveAssignment) {
                    UserFunnel::create([
                        'user_id'         => $userId,
                        'patient_id'      => $request->patient_id,
                        'funnel_id'       => $funnelId,
                        'patient_case_id' => $patientCase->id,
                        'assigned_via'    => 'email',
                        'assigned_at'     => now(),
                        'email'           => $request->email,
                    ]);
                }

                $funnelMail = new AssignFunnelMail(
                    $request->patient_id,
                    $request->case_id,
                    $funnelId,
                    $funnelName,
                    $patientName,
                    $request->email,
                    $request->phone,
                    $flag,
                    'email',
                    count($request->funnels) > 1,
                    'emails.multiple-assign-funnel'
                );

                // Only one email is sent per multiple-assign-funnel request. The first
                // successfully-assigned funnel's link is used; the frontend resolves the
                // rest of the pending funnels for this patient/case via is_multiple_funnels.
                if (!$primaryMail) {
                    $primaryMail = $funnelMail;
                }

                $results[] = [
                    'funnel_id'   => $funnelId,
                    'funnel_name' => $funnelName,
                    'status'      => 'assigned',
                    'message'     => 'Funnel assigned successfully.',
                    'url'         => $funnelMail->funnelUrl,
                ];
            }

            if ($primaryMail) {
                Mail::to($request->email)->send($primaryMail);
            }

            DB::commit();

            Log::channel('patient_funnel')->info('Multiple funnels assigned successfully', [
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
                'user_id'    => $userId,
                'flag'       => $flag,
                'results'    => $results,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Funnels processed successfully.',
                'results' => $results,
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::channel('patient_funnel')->error('Error assigning multiple funnels', [
                'patient_id' => $request->patient_id ?? null,
                'case_id'    => $request->case_id ?? null,
                'message'    => $e->getMessage(),
                'line'       => $e->getLine(),
                'file'       => $e->getFile(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while assigning the funnels.',
            ], 500);
        }
    }

    /**
     * POST /api/multiple-assign-funnel-sms
     *
     * Assigns multiple funnels to a patient/case in a single request and sends
     * one assignment SMS per funnel. Per-funnel behavior (existing-assignment
     * guard, consent expiry, UserFunnel creation) is identical to assignFunnelSms().
     *
     * Request Payload:
     * - patient_id (required, int, exists in ahcs.ahcs_patients)
     * - case_id (required, int, exists in ahcs.ahcs_cases)
     * - phone (required, string, max:20)
     * - email (nullable, valid email, max:255) — stored in user_funnels and used for user lookup
     * - funnels (required, array, min:1)
     *   - funnels.*.funnel_id (required, int, exists in funnels)
     *   - funnels.*.funnel_name (required, string, max:255)
     *
     * Example payload:
     * {
     *   "patient_id": 123,
     *   "case_id": 456,
     *   "phone": "5551234567",
     *   "email": "patient@example.com",
     *   "funnels": [
     *     { "funnel_id": 1, "funnel_name": "NPPW Consent" },
     *     { "funnel_id": 2, "funnel_name": "Health Questionnaire" }
     *   ]
     * }
     *
     * Response:
     * - 200: { status: true, message: string, results: [{ funnel_id, funnel_name, status, message, url? }] }
     * - 422: { status: false, message: string, errors: string } (validation failed)
     * - 422: { status: false, message: string, error_code: int|null } (Twilio rejected
     *        the send; message is Twilio's own wording, e.g. "Invalid 'To' Phone Number: …")
     * - 500: { status: false, message: string }
     */
    public function multipleAssignFunnelSms(Request $request)
    {
        try {
            Log::channel('patient_funnel')->info('Multiple assign funnel SMS request received', [
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
                'funnels'    => $request->funnels,
            ]);

            $validator = Validator::make($request->all(), [
                'patient_id'            => 'required|integer|exists:ahcs.ahcs_patients,id',
                'case_id'               => 'required|integer|exists:ahcs.ahcs_cases,id',
                'phone'                 => 'required|string|max:20',
                'email'                 => 'nullable|email|max:255',
                'funnels'               => 'required|array|min:1',
                'funnels.*.funnel_id'   => 'required|integer|exists:funnels,id',
                'funnels.*.funnel_name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                Log::channel('patient_funnel')->warning('Multiple assign funnel SMS validation failed', [
                    'patient_id' => $request->patient_id,
                    'case_id'    => $request->case_id,
                    'error'      => $validator->errors()->first(),
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->errors()->first(),
                ], 422);
            }

            DB::beginTransaction();
            $normalizedPhone = $this->normalizePhoneForSms((string) $request->phone);
            $normalizedDigits = preg_replace('/\D+/', '', $normalizedPhone);
            if (strlen($normalizedDigits) < 11) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation failed.',
                    'errors'  => 'Please provide a valid phone number.',
                ], 422);
            }

            $patient = AhcsPatient::find($request->patient_id);

            // Check for an existing user by email OR patient_id membership
            // (handles both old plain-int and new JSON-array storage formats).
            $user = $request->filled('email')
                ? User::where('email', $request->email)
                      ->orWhere(function ($q) use ($request) {
                          $pid = (int) $request->patient_id;
                          $q->whereJsonContains('patient_id', $pid)
                            ->orWhere('patient_id', $pid);
                      })
                      ->first()
                : User::hasPatientId((int) $request->patient_id)->first();

            $userId = $user?->id;
            $flag   = $user ? 'user_exists' : 'no_user';
            $patientName = $patient->patient_name
                ?? $user?->name
                ?? 'Patient';

            // If user already exists, append the patient_id to their array without
            // overwriting any previously stored patient IDs.
            if ($user) {
                $user->appendPatientId((int) $request->patient_id);
            }

            $patientCase = PatientCase::firstOrCreate([
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
            ]);

            $twilioSid = config('services.twilio.sid');
            $twilioToken = config('services.twilio.token');
            $twilioFrom = config('services.twilio.from');

            if (empty($twilioSid) || empty($twilioToken) || empty($twilioFrom)) {
                throw new RuntimeException('Twilio SMS configuration is missing.');
            }

            $results        = [];
            $primaryFunnelUrl = null;

            foreach ($request->funnels as $funnelInput) {
                $funnelId   = (int) $funnelInput['funnel_id'];
                $funnelName = $funnelInput['funnel_name'];

                // Guard: reject if an ACTIVE (non-deleted) assignment of THIS SAME funnel
                // already exists for this patient + case. Multiple different funnels can
                // be assigned to the same patient case — only re-assigning the same
                // funnel is blocked. A previously DELETED assignment is intentionally
                // ignored here — re-assigning after deletion must always start fresh.

                $existingActiveAssignment = UserFunnel::where('patient_id', $request->patient_id)
                    ->where('patient_case_id', $patientCase->id)
                    ->where('funnel_id', $funnelId)
                    ->first();

                if ($existingActiveAssignment) {
                    $assignedFunnel = Funnel::find($existingActiveAssignment->funnel_id);
                    $funnelFormIds  = $assignedFunnel
                        ? (is_array($assignedFunnel->form_ids)
                            ? $assignedFunnel->form_ids
                            : json_decode($assignedFunnel->form_ids ?? '[]', true))
                        : [];
                    $funnelFormIds = is_array($funnelFormIds) ? $funnelFormIds : [];

                    $completedCount = count($funnelFormIds) > 0
                        ? FormSubmission::where('user_funnel_id', $existingActiveAssignment->id)
                            ->whereIn('form_id', $funnelFormIds)
                            ->where('status', 'completed')
                            ->distinct('form_id')
                            ->count('form_id')
                        : 0;

                    if (count($funnelFormIds) > 0 && $completedCount >= count($funnelFormIds)) {
                        if ($this->isExpiredConsentAssignment($assignedFunnel, $existingActiveAssignment, $funnelFormIds)) {
                            // Consent forms must be resigned after 1 year (health questionnaires and
                            // other non-consent funnels never expire). Treat this like there was no
                            // active assignment so a fresh one gets created and sent below.
                            Log::channel('patient_funnel')->info('Consent funnel expired (signed over 1 year ago); reassigning.', [
                                'patient_id' => $request->patient_id,
                                'case_id'    => $request->case_id,
                                'funnel_id'  => $funnelId,
                            ]);
                            $existingActiveAssignment = null;
                        } else {
                            Log::channel('patient_funnel')->info('Funnel already completed for this patient case.', [
                                'patient_id' => $request->patient_id,
                                'case_id'    => $request->case_id,
                                'funnel_id'  => $funnelId,
                                'user_id'    => $existingActiveAssignment->user_id,
                            ]);
                            $results[] = [
                                'funnel_id'   => $funnelId,
                                'funnel_name' => $funnelName,
                                'status'      => 'already_completed',
                                'message'     => 'Funnel is already completed for this patient case.',
                            ];
                            continue;
                        }
                    }

                    if ($existingActiveAssignment) {
                        Log::channel('patient_funnel')->warning('Active funnel assignment already exists for this patient case. Sending reminder.', [
                            'patient_id' => $request->patient_id,
                            'case_id'    => $request->case_id,
                            'funnel_id'  => $funnelId,
                            'user_id'    => $existingActiveAssignment->user_id,
                        ]);
                    }
                }

                // Always create a brand-new UserFunnel record.
                //
                // WHY: Restoring a soft-deleted record reuses the same primary key
                // (user_funnel_id). All FormSubmissions previously linked to that ID
                // would instantly re-appear, making the patient look like they already
                // completed everything even though the assignment was deleted and
                // re-created intentionally. A new record = new ID = clean slate.
                if (!$existingActiveAssignment) {
                    UserFunnel::create([
                        'user_id'         => $userId,
                        'patient_id'      => $request->patient_id,
                        'funnel_id'       => $funnelId,
                        'patient_case_id' => $patientCase->id,
                        'assigned_via'    => 'sms',
                        'assigned_at'     => now(),
                        'email'           => $request->email ?: null,
                        'phone_no'        => $normalizedPhone,
                    ]);
                } else {
                    // Re-sending an existing assignment over SMS: see assignFunnelSms().
                    // A row created by an email assignment has phone_no NULL, which makes
                    // the source=sms registration lookup miss it.
                    $existingActiveAssignment->forceFill([
                        'phone_no'     => $normalizedPhone,
                        'assigned_via' => 'sms',
                        'email'        => $existingActiveAssignment->email ?: ($request->email ?: null),
                    ])->save();
                }

                // Prefer any email we already hold for this patient so the registration
                // form can prefill it.
                $linkEmail = (string) (
                    $user?->email
                    ?: $existingActiveAssignment?->email
                    ?: $request->email
                    ?: ''
                );

                $funnelUrl = (new AssignFunnelMail(
                    (string) $request->patient_id,
                    (string) $request->case_id,
                    (string) $funnelId,
                    (string) $funnelName,
                    (string) $patientName,
                    $linkEmail,
                    (string) $normalizedPhone,
                    (string) $flag,
                    'sms',
                    count($request->funnels) > 1
                ))->funnelUrl;

                // Only one SMS is sent per multiple-assign-funnel-sms request. The first
                // successfully-assigned funnel's link is used; the frontend resolves the
                // rest of the pending funnels for this patient/case via is_multiple_funnels.
                if ($primaryFunnelUrl === null) {
                    $primaryFunnelUrl = $funnelUrl;
                }

                $results[] = [
                    'funnel_id'   => $funnelId,
                    'funnel_name' => $funnelName,
                    'status'      => 'assigned',
                    'message'     => 'Funnel assigned successfully.',
                    'url'         => $funnelUrl,
                ];
            }

            if ($primaryFunnelUrl !== null) {
                $smsBody = "Hello, {$patientName}.\n"
                    . "You have received a new funnel form link. Please use the link below to access and complete the form.\n"
                    . "Click here to open your funnel form: {$primaryFunnelUrl}\n"
                    . "If you have any questions, feel free to contact support.\n\n"
                    . "Best Regards,\n"
                    . "MedHiWa Team";

                $smsResponse = Http::withBasicAuth($twilioSid, $twilioToken)
                    ->asForm()
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json", [
                        'From' => $twilioFrom,
                        'To'   => $normalizedPhone,
                        'Body' => $smsBody,
                    ]);

                if ($smsResponse->failed()) {
                    throw SmsDeliveryException::fromTwilioResponse($smsResponse);
                }
            }

            DB::commit();

            Log::channel('patient_funnel')->info('Multiple funnels assigned and SMS sent successfully', [
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
                'user_id'    => $userId,
                'flag'       => $flag,
                'results'    => $results,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Funnels processed successfully.',
                'results' => $results,
            ], 200);

        } catch (SmsDeliveryException $e) {
            // See assignFunnelSms(): surface Twilio's own rejection reason.
            DB::rollBack();

            Log::channel('patient_funnel')->error('SMS delivery rejected while assigning multiple funnels', [
                'patient_id'      => $request->patient_id ?? null,
                'case_id'         => $request->case_id ?? null,
                'twilio_code'     => $e->twilioCode,
                'twilio_response' => $e->rawBody,
            ]);

            return response()->json([
                'status'     => false,
                'message'    => $e->getMessage(),
                'error_code' => $e->twilioCode,
            ], 422);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::channel('patient_funnel')->error('Error assigning multiple funnels via SMS', [
                'patient_id' => $request->patient_id ?? null,
                'case_id'    => $request->case_id ?? null,
                'message'    => $e->getMessage(),
                'line'       => $e->getLine(),
                'file'       => $e->getFile(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while assigning the funnels via SMS.',
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
            $source = $request->input('source', 'email'); // 'email' | 'sms'

            Log::channel('patient_funnel')->info('Add patient to funnel request received', [
                'patient_id' => $request->patient_id,
                'case_id'    => $request->case_id,
                'funnel_id'  => $request->funnel_id,
                'source'     => $source,
            ]);

            // ── Validation ──────────────────────────────────────────────────────
            $rules = [
                'patient_id'       => 'required|integer',
                'case_id'          => 'required|integer',
                'funnel_id'        => 'required|integer',
                'source'           => 'required|string|in:email,sms',
                'name'             => 'required|string|max:255',
                'phone'            => 'nullable|string|max:20',
                'password'         => [
                    'required',
                    'string',
                    Password::min(8)
                        ->mixedCase()
                        ->letters()
                        ->numbers()
                        ->symbols(),
                ],
                'confirm_password' => 'required|string|same:password',
            ];

            // email is required only for email-based registration
            if ($source === 'email') {
                $rules['email'] = 'required|email|max:255';
            } else {
                $rules['email'] = 'nullable|email|max:255';
                // For SMS source, phone is required and must include enough digits
                $rules['phone'] = 'required|string|max:20';
            }

            $validator = Validator::make($request->all(), $rules);

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
                ], 422);
            }

            // ── Extra phone validation for SMS source ────────────────────────────
            if ($source === 'sms') {
                $normalizedPhone  = $this->normalizePhoneForSms((string) $request->phone);
                $normalizedDigits = preg_replace('/\D+/', '', $normalizedPhone);
                if (strlen($normalizedDigits) < 11) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Please provide a valid phone number.',
                    ], 422);
                }
            }

            // ── Check patient ────────────────────────────────────────────────────
            $patient = AhcsPatient::find($request->patient_id);
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found.'], 404);
            }

            // ── Check case ───────────────────────────────────────────────────────
            if (!AhcsCase::find($request->case_id)) {
                return response()->json(['status' => false, 'message' => 'Case not found.'], 404);
            }

            // ── Check funnel ─────────────────────────────────────────────────────
            if (!Funnel::find($request->funnel_id)) {
                return response()->json(['status' => false, 'message' => 'Funnel not found.'], 404);
            }

            // ── Check funnel assignment exists ───────────────────────────────────
            $assignmentQuery = UserFunnel::where('patient_id', $request->patient_id)
                ->where('funnel_id', $request->funnel_id);

            if ($source === 'sms') {
                // For SMS: match by patient_id + phone_no stored during assignFunnelSms.
                // Rows created by an email assignment and later re-sent over SMS may
                // still have phone_no NULL, so accept those too rather than 404-ing a
                // patient who is holding a perfectly valid link.
                $assignmentQuery->where(function ($q) use ($normalizedPhone) {
                    $q->where('phone_no', $normalizedPhone)
                      ->orWhereNull('phone_no');
                });
            }

            // Existence gate only. Registration below creates the account and links
            // EVERY assignment row for this patient (matched on patient_id + email or
            // phone_no), so no single row is singled out here and picking one would be
            // misleading. Deliberately not narrowed to patient_case_id either: an
            // account is patient-level rather than case-level, and patient_case_id is
            // nullable on legacy rows, so filtering on it would only reintroduce the
            // spurious 404 this change exists to remove.
            $hasAssignment = $assignmentQuery->exists();

            if (!$hasAssignment) {
                Log::channel('patient_funnel')->warning('Add patient to funnel failed: assignment not found', [
                    'patient_id' => $request->patient_id,
                    'funnel_id'  => $request->funnel_id,
                    'source'     => $source,
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'User funnel assignment not found.',
                ], 404);
            }

            DB::beginTransaction();

            $requestPatientId = (int) $request->patient_id;

            // ── Source: EMAIL ────────────────────────────────────────────────────
            if ($source === 'email') {

                // Keep AHCS patient email in sync.
                AhcsPatient::where('id', $requestPatientId)->update([
                    'email' => $request->email,
                ]);

                // Collect every patient_id from user_funnels rows whose email matches
                // the registering patient so all previous assignments consolidate into
                // one user account.
                $funnelPatientIds = UserFunnel::withTrashed()
                    ->where('email', $request->email)
                    ->pluck('patient_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->toArray();

                // Find existing user by email OR patient_id membership.
                $user = User::withTrashed()
                    ->where('email', $request->email)
                    ->orWhere(function ($q) use ($requestPatientId) {
                        $q->whereJsonContains('patient_id', $requestPatientId)
                          ->orWhere('patient_id', $requestPatientId);
                    })
                    ->first();

                $existingIds = $user ? $user->getAllPatientIds() : [];
                $mergedIds   = array_values(array_unique(array_merge(
                    array_map('intval', $existingIds),
                    $funnelPatientIds,
                    [$requestPatientId],
                )));

                if ($user) {
                    if ($user->trashed()) {
                        $user->restore();
                    }
                    $user->update([
                        'patient_id'        => $mergedIds,
                        'name'              => $request->name,
                        'email'             => $request->email,
                        'phone'             => $request->phone,
                        'password'          => bcrypt($request->password),
                        'country_code'      => 'US',
                        'email_verified_at' => now(),
                        'phone_verified_at' => now(),
                    ]);
                } else {
                    $user = User::create([
                        'patient_id'        => $mergedIds,
                        'name'              => $request->name,
                        'email'             => $request->email,
                        'phone'             => $request->phone,
                        'password'          => bcrypt($request->password),
                        'country_code'      => 'US',
                        'email_verified_at' => now(),
                        'phone_verified_at' => now(),
                    ]);
                }

                // Link ALL funnel rows for this patient or email to the user.
                $updatedUserFunnelRows = UserFunnel::withTrashed()
                    ->where(function ($q) use ($requestPatientId, $request) {
                        $q->where('patient_id', $requestPatientId)
                          ->orWhere('email', $request->email);
                    })
                    ->update(['user_id' => $user->id]);

                // AMD sync — keep email up to date
                $amdSyncPayload = ['email' => $request->email];

            // ── Source: SMS ──────────────────────────────────────────────────────
            } else {

                // Keep AHCS patient email in sync.
                AhcsPatient::where('id', $requestPatientId)->update([
                    'email' => $request->email,
                ]);

                // Collect every patient_id from user_funnels rows whose phone_no
                // matches the registering patient's normalised phone number, so all
                // previous SMS assignments consolidate into one user account.
                $funnelPatientIds = UserFunnel::withTrashed()
                    ->where('phone_no', $normalizedPhone)
                    ->pluck('patient_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->toArray();

                // Find existing user by email (if provided) OR patient_id membership.
                $user = User::withTrashed()
                    ->when($request->filled('email'), fn ($q) => $q->where('email', $request->email))
                    ->orWhere(function ($q) use ($requestPatientId) {
                        $q->whereJsonContains('patient_id', $requestPatientId)
                          ->orWhere('patient_id', $requestPatientId);
                    })
                    ->first();

                $existingIds = $user ? $user->getAllPatientIds() : [];
                $mergedIds   = array_values(array_unique(array_merge(
                    array_map('intval', $existingIds),
                    $funnelPatientIds,
                    [$requestPatientId],
                )));

                if ($user) {
                    if ($user->trashed()) {
                        $user->restore();
                    }
                    $user->update([
                        'patient_id'        => $mergedIds,
                        'name'              => $request->name,
                        'email'             => $request->filled('email') ? $request->email : $user->email,
                        'phone'             => $request->phone,
                        'password'          => bcrypt($request->password),
                        'country_code'      => 'US',
                        'email_verified_at' => now(),
                        'phone_verified_at' => now(),
                    ]);
                } else {
                    $user = User::create([
                        'patient_id'        => $mergedIds,
                        'name'              => $request->name,
                        'email'             => $request->input('email'),
                        'phone'             => $request->phone,
                        'password'          => bcrypt($request->password),
                        'country_code'      => 'US',
                        'email_verified_at' => now(),
                        'phone_verified_at' => now(),
                    ]);
                }

                // Link ALL funnel rows for this patient or phone_no to the user.
                $updatedUserFunnelRows = UserFunnel::withTrashed()
                    ->where(function ($q) use ($requestPatientId, $normalizedPhone) {
                        $q->where('patient_id', $requestPatientId)
                          ->orWhere('phone_no', $normalizedPhone);
                    })
                    ->update(['user_id' => $user->id]);

                // AMD sync — keep phone and email up to date
                $amdSyncPayload = ['cell_no' => $request->phone];
                if ($request->filled('email')) {
                    $amdSyncPayload['email'] = $request->email;
                }
            }

            DB::commit();

            // ── AMD sync (outside transaction — non-critical) ────────────────────
            $amdSyncResult = null;
            try {
                $amdSyncService = app(PatientFormAmdSyncService::class);
                $amdSyncResult  = $amdSyncService->syncDemographics(
                    (int) $request->patient_id,
                    (int) $request->case_id,
                    $amdSyncPayload,
                    $patient->toArray()
                );
            } catch (\Throwable $amdError) {
                $amdSyncResult = ['status' => 'failed', 'message' => 'Something went wrong'];

                Log::channel('patient_funnel')->error('AMD sync failed after patient added to funnel', [
                    'patient_id' => $request->patient_id,
                    'case_id'    => $request->case_id,
                    'error'      => $amdError->getMessage(),
                ]);
            }

            Log::channel('patient_funnel')->info('Patient added to funnel successfully', [
                'patient_id'              => $request->patient_id,
                'case_id'                 => $request->case_id,
                'funnel_id'               => $request->funnel_id,
                'source'                  => $source,
                'user_id'                 => $user->id,
                'amd_sync'                => $amdSyncResult,
                'updated_user_funnel_rows'=> $updatedUserFunnelRows,
            ]);

            return response()->json([
                'status'   => true,
                'message'  => 'Patient added to funnel successfully.',
                'data'     => [
                    'user_id'    => $user->id,
                    'patient_id' => $user->patient_id,
                    'funnel_id'  => $request->funnel_id,
                ],
                'amd_sync' => $amdSyncResult,
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::channel('patient_funnel')->error('Error adding patient to funnel', [
                'patient_id' => $request->patient_id ?? null,
                'funnel_id'  => $request->funnel_id ?? null,
                'source'     => $request->input('source') ?? null,
                'message'    => $e->getMessage(),
                'line'       => $e->getLine(),
                'file'       => $e->getFile(),
            ]);

            return response()->json([
                'status'  => false,
                'error'   => $e->getMessage(),
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
                ->get(['id', 'name', 'insurance_type']);

            $groupedFunnels = [
                'NPPW' => [],
                'Consent' => [],
                'Other' => [],
                'Test' => []
            ];

            foreach ($funnels as $funnel) {

                $name = strtolower($funnel->name);

                if (str_contains($name, 'nppw')) {

                    $groupedFunnels['NPPW'][] = $funnel;

                } elseif (str_contains($name, 'consent')) {

                    $groupedFunnels['Consent'][] = $funnel;

                } elseif (str_contains($name, 'test')) {

                    $groupedFunnels['Test'][] = $funnel;

                } else {

                    $groupedFunnels['Other'][] = $funnel;
                }
            }

            Log::channel('patient_funnel')->info('Fetching all active funnels - Success', [
                'total_funnels' => $funnels->count(),
                'nppw_count'    => count($groupedFunnels['NPPW']),
                'consent_count' => count($groupedFunnels['Consent']),
                'test_count'    => count($groupedFunnels['Test']),
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
