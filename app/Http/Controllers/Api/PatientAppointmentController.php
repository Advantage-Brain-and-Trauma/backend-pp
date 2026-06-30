<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AhcsPatient;
use App\Models\AhcsCase;
use App\Models\AhcsMedAuth;
use App\Models\AhcsAttendance;
use App\Models\MedhiwaSpecialityLocation;
use App\Models\Physician;
use App\Models\PhysicianAddress;
use App\Models\PhysicianProvierMonthlyAvailability;
use App\Models\PhysicianCustomLunchTime;
use App\Models\PhysicianSpeciality;
use App\Models\MedhiwaSpecialityVisitType;
use App\Models\MedhiwaAmdProviderCompanyMapping;
use App\Models\MedhiwaAttendance;
use App\Models\MedhiwaSpeciality;
use App\Models\MedhiwaCareNewOrderType;
use App\Models\PatientPortalPreauthMissingDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PatientAppointmentController extends Controller
{
    private string $STORAGE_BASE;
    private string $LOCAL_WEBDAV_BASE;
    private const WEBDAV_BASE = 'http://10.0.0.24/webdav/mh';

    public function __construct()
    {
        $this->STORAGE_BASE     = config('services.app_server.storage_url');
        $this->LOCAL_WEBDAV_BASE = config('services.app_server.webdav_url');
    }

    /**
     * GET /api/get-patient-appointments
     *
     * Returns upcoming and past appointments for the authenticated patient.
     *
     * Request Payload:
     * - case_id (optional, integer)
     *
     * Response:
     * - 200: { success: true, upcoming_count, past_count, upcoming_appointments: array, past_appointments: array }
     * - 404: { success: false, message: string }
     * - 500: { success: false, message: string }
     */
    public function getPatientAppointments(Request $request): JsonResponse
    {
        try {
            Log::channel('appointment')->info('Fetching patient appointments - Start');
            $user       = auth()->user();
            $patientIds = $user->getActivePatientIds();
            $caseId     = $request->input('case_id');

            if (empty($patientIds)) {
                throw new Exception("Patient ID is required", 400);
            }

            if (empty($caseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case Id is required',
                ], 422);
            }

            // Validate the case belongs to one of this user's patient IDs and
            // resolve the specific patient_id for logging / patient lookup.
            $caseRecord = AhcsCase::where('id', $caseId)
                ->whereIn('patient_id', $patientIds)
                ->first(['id', 'patient_id']);

            if (!$caseRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Case Id for this patient',
                ], 422);
            }

            $patientId = $caseRecord->patient_id;

            // ✅ Check the resolved patient exists
            AhcsPatient::findOrFail($patientId);
            Log::channel('appointment')->info('Patient found', ['patient_id' => $patientId]);

            // Fetch all case IDs across every patient linked to this user,
            // filtered to the requested case_id when provided.
            $caseQuery = AhcsCase::whereIn('patient_id', $patientIds);

            if (!empty($caseId)) {
                $caseQuery->where('id', $caseId);
            }

            $caseIds = $caseQuery->pluck('id');

            if ($caseIds->isEmpty()) {
                throw new Exception("No cases found for this patient", 404);
            }
            Log::channel('appointment')->info('Case IDs fetched', ['patient_id' => $patientId, 'case_ids' => $caseIds->toArray()]);

            // ✅ Get all MedAuth IDs for those cases
            $medAuthIds = AhcsMedAuth::whereIn('case_id', $caseIds)->pluck('id');
            Log::channel('appointment')->info('MedAuth IDs fetched', ['patient_id' => $patientId, 'med_auth_ids' => $medAuthIds->toArray()]);

            if ($medAuthIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No MedAuth records found',
                    'upcoming_count' => 0,
                    'past_count' => 0,
                    'upcoming_appointments' => [],
                    'past_appointments' => []
                ], 200);
            }

            // ✅ Fetch appointments
            $appointments = AhcsAttendance::whereIn('ma_id', $medAuthIds)
                ->whereNotIn('attend_status', ['DL', 'Block','RS'])
                ->get([
                    'id','ma_id','department','service','attend_type',
                    'provider_id','provider_name','attend_date','time',
                    'end_time','length','attend_status','attend_notes','is_virtual','transport'
                ]);
            Log::channel('appointment')->info('Appointments fetched', ['patient_id' => $patientId, 'appointment_count' => $appointments->count()]);

            $attachmentByAttendId = DB::connection('ahcs')
                ->table('ahcs_attachment_logs')
                ->select('id', 'case_id', 'attend_id', 'folder', 'sub_folder', 'filename', 'serverType')
                ->where('case_id', $caseId)
                ->whereIn('attend_id', $appointments->pluck('id')->all())
                ->orderByDesc('id')
                ->get()
                ->unique('attend_id')
                ->keyBy('attend_id');

            if ($appointments->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No appointments found',
                    'upcoming_count' => 0,
                    'past_count' => 0,
                    'upcoming_appointments' => [],
                    'past_appointments' => []
                ], 200);
            }

            // ✅ Load mappings
            $specialities = MedhiwaSpeciality::pluck('name', 'short_name');
            $attendTypes = MedhiwaCareNewOrderType::pluck('name', 'code')
                        ->mapWithKeys(function ($value, $key) {
                            return [strtolower($key) => $value];
                        });

            // ✅ Map names
            $appointments->transform(function ($appointment) use ($specialities, $attendTypes, $caseId, $attachmentByAttendId) {
                $appointment->service_full_name = $specialities[$appointment->service] ?? null;
                $code = strtolower($appointment->attend_type);
                $appointment->attend_type_full_name = $attendTypes[$code] ?? null;

                $appointment->is_virtual_text = $appointment->is_virtual == 1
                    ? 'Telehealth'
                    : 'In-Person';

                $appointment->appt_status = 'Confirmed';
                $attachment = $attachmentByAttendId->get($appointment->id);
                $resolvedAttachmentUrl = $attachment
                    ? $this->resolvePreferredAttachmentUrl($attachment)
                    : null;
                $appointment->clinical_note = $resolvedAttachmentUrl;

                return $appointment;
            });

            // ✅ Split upcoming & past
            $now = now();

            $upcoming = collect();
            $past = collect();

            foreach ($appointments as $appointment) {
                $appointmentDateTime = Carbon::parse(
                    $appointment->attend_date . ' ' . $appointment->time
                );

                if ($appointmentDateTime->gte($now)) {
                    $upcoming->push($appointment);
                } else {
                    $past->push($appointment);
                }
            }
            Log::channel('appointment')->info('Appointments split into upcoming and past', ['patient_id' => $patientId, 'upcoming_count' => $upcoming->count(), 'past_count' => $past->count()]);

            return response()->json([
                'success' => true,
                'upcoming_count' => $upcoming->count(),
                'past_count' => $past->count(),
                'upcoming_appointments' => $upcoming,
                'past_appointments' => $past
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found'
            ], 404);

        } catch (\Throwable $e) {
            Log::channel('appointment')->error("Error fetching patient appointments: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong'
            ],500);
        }
    }

    private function resolvePreferredAttachmentUrl(object $row): string
    {
        $caseId = (string) ($row->case_id ?? '');
        $folder = trim((string) ($row->folder ?? ''), '/\\');
        $subFolder = trim((string) ($row->sub_folder ?? ''), '/\\');
        $filename = trim((string) ($row->filename ?? ''), '/\\');
        $attendId = (string) ($row->attend_id ?? '');
        $serverType = (string) ($row->serverType ?? '2');

        if ($caseId === '' || $folder === '' || $filename === '') {
            return '';
        }

        $split = implode('/', str_split($caseId));

        if ($serverType === '1') {
            $bases = [self::WEBDAV_BASE, $this->LOCAL_WEBDAV_BASE, $this->STORAGE_BASE];
        } elseif ($serverType === '2') {
            $bases = [$this->STORAGE_BASE, $this->LOCAL_WEBDAV_BASE, self::WEBDAV_BASE];
        } elseif ($attendId === '' || $attendId === '0') {
            $bases = [$this->STORAGE_BASE, $this->LOCAL_WEBDAV_BASE, self::WEBDAV_BASE];
        } else {
            $bases = [$this->STORAGE_BASE, $this->LOCAL_WEBDAV_BASE, self::WEBDAV_BASE];
        }

        $folderVariants = array_values(array_unique([$folder, strtolower($folder), strtoupper($folder)]));
        $subVariants = array_values(array_unique([$subFolder, strtolower($subFolder), strtoupper($subFolder)]));
        if ($subFolder === '') {
            $subVariants = [''];
        }

        foreach ($bases as $base) {
            $base = rtrim($base, '/');
            foreach ($folderVariants as $f) {
                foreach ($subVariants as $s) {
                    $path = $s !== ''
                        ? "{$base}/{$split}/{$f}/{$s}/{$filename}"
                        : "{$base}/{$split}/{$f}/{$filename}";
                    if ($path !== '') {
                        return $path;
                    }
                }
            }
        }

        return '';
    }


    /**
     * GET /api/get-appointment-departments
     *
     * Returns active appointment departments (cities).
     *
     * Request Payload:
     * - None
     *
     * Response:
     * - 200: { success: true, departments: array }
     * - 500: { success: false, message: string }
     */
    public function getAppointmentDepartments(){
        try {
            Log::channel('appointment')->info('Fetching appointment departments - Start');

            $departments = MedhiwaSpecialityLocation::where('status', 1)
                            ->whereNull('deleted_at')
                            ->pluck('city');

            Log::channel('appointment')->info('Fetching appointment departments - Success', [
                'department_count' => $departments->count(),
            ]);
            
            return response()->json([
                'success' => true,
                'departments' => $departments
            ], 200);
        } catch (\Throwable $e) {
            Log::channel('appointment')->error("Error fetching appointment departments: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    /**
     * GET /api/get-department-speciality-with-physician?department={city}
     *
     * Returns specialities, visit types, and physician availability details for a department.
     *
     * Request Payload:
     * - Query: department (required, string)
     *
     * Response:
     * - 200: { success: true, count: int, data: array }
     * - 400: { success: false, message: string, data: [] }
     */
    public function getDepartmentSpecialityWithPhysician(Request $request)
    {
        $department = $request->query('department');
        Log::channel('appointment')->info('Fetching department speciality with physician - Start', [
            'department' => $department,
        ]);

        if (!$department) {
            Log::channel('appointment')->warning('Department speciality fetch failed: missing department');
            return response()->json([
                'success' => false,
                'message' => 'Department parameter is required',
                'data' => []
            ], 400);
        }

        /* ------------------------------------
         | 1. Get mapped specialities for this location
         |-------------------------------------*/
        $rows = MedhiwaSpecialityLocation::getLocationsWithSpecialities()
            ->where('city', $department);

        if ($rows->isEmpty()) {
            Log::channel('appointment')->info('No speciality rows found for department', [
                'department' => $department,
            ]);
            return response()->json([
                'success' => true,
                'count' => 0,
                'data' => []
            ]);
        }

        /* ------------------------------------
         | 2. Build unique speciality list
         |-------------------------------------*/
        $specialities = $rows
            ->unique('speciality_id')
            ->map(function ($row) {
                return (object) [
                    'id' => $row->speciality_id,
                    'name' => $row->name,
                    'short_name' => $row->short_name,
                    'allow_multiple' => $row->allow_multiple,
                    'multiple_allowed_slots' => $row->multiple_allowed_slots,
                    'multiple_slot_duration' => $row->multiple_slot_duration,
                    'parent_id' => $row->parent_id ?? null,
                ];
            })
            ->keyBy('short_name');

        /* ------------------------------------
         | 3. Get ACTIVE Physicians for current location
         | - Filter by is_active = 1 (only active providers)
         | - Filter by physician_type = 'internal'
         | - Get working hours for current location
         |-------------------------------------*/
        $physicians = Physician::query()
            ->from('physicians as p')
            ->join('physician_addresses as pa', function ($join) {
                $join->on('pa.physician_id', '=', 'p.id')
                    ->whereNull('pa.deleted_at');
            })
            ->where('pa.physician_city', $department)
            ->where('p.physician_type', 'internal')
            ->where('p.is_deleted', 1)
            ->where('p.is_active', 1)
            ->select(
                'p.id as physician_id',
                'p.physician_name',
                'p.speciality_short',
                'p.is_aids_tech',
                'p.schedule_type',

                'pa.amd_physician_code as provider_code',

                'pa.physician_sun',
                'pa.physician_mon',
                'pa.physician_tue',
                'pa.physician_wed',
                'pa.physician_thu',
                'pa.physician_fri',
                'pa.physician_sat',

                'pa.physician_sun_open',
                'pa.physician_mon_open',
                'pa.physician_tue_open',
                'pa.physician_wed_open',
                'pa.physician_thu_open',
                'pa.physician_fri_open',
                'pa.physician_sat_open',

                'pa.physician_sun_close',
                'pa.physician_mon_close',
                'pa.physician_tue_close',
                'pa.physician_wed_close',
                'pa.physician_thu_close',
                'pa.physician_fri_close',
                'pa.physician_sat_close',

                'pa.lunch_time_start',
                'pa.lunch_time_end',
                'pa.lunch_time_enabled',

                'pa.is_telemed',

                'pa.telemed_sun',
                'pa.telemed_mon',
                'pa.telemed_tue',
                'pa.telemed_wed',
                'pa.telemed_thu',
                'pa.telemed_fri',
                'pa.telemed_sat'
            )
            ->get();

       

        /* ------------------------------------
         | 4. Get OTHER location addresses for multi-location providers
         | Fetch all addresses for providers who work at the current location
         | but exclude the current location itself
         |-------------------------------------*/
        $physicianIds = $physicians->pluck('physician_id')->unique()->toArray();

        $otherLocations = collect();
        if (!empty($physicianIds)) {
            $otherLocations = PhysicianAddress::whereIn('physician_id', $physicianIds)
                ->where('physician_city', '!=', $department) // Exclude current location
                ->whereNull('deleted_at') // Soft delete filter
                ->select(
                    'physician_id',
                    'physician_city',
                    'amd_physician_code as provider_code',
                    'physician_sun',
                    'physician_mon',
                    'physician_tue',
                    'physician_wed',
                    'physician_thu',
                    'physician_fri',
                    'physician_sat',
                    'physician_sun_open',
                    'physician_mon_open',
                    'physician_tue_open',
                    'physician_wed_open',
                    'physician_thu_open',
                    'physician_fri_open',
                    'physician_sat_open',
                    'physician_sun_close',
                    'physician_mon_close',
                    'physician_tue_close',
                    'physician_wed_close',
                    'physician_thu_close',
                    'physician_fri_close',
                    'physician_sat_close',
                    'lunch_time_start',
                    'lunch_time_end',
                    'lunch_time_enabled',
                    'is_telemed',
                    // Day-wise telemed flags
                    'telemed_sun',
                    'telemed_mon',
                    'telemed_tue',
                    'telemed_wed',
                    'telemed_thu',
                    'telemed_fri',
                    'telemed_sat'
                )
                ->get()
                ->groupBy('physician_id');
        }

        $monthlyAvailabilities = PhysicianProvierMonthlyAvailability::whereIn('provider_id', $physicianIds)
                                ->where('provider_city', $department)
                                ->select('provider_id', 'available_date as date', 'open_time', 'close_time', 'is_telemed')
                                ->orderBy('available_date')
                                ->get()
                                ->groupBy('provider_id');

        $customLunchTimes = PhysicianCustomLunchTime::whereIn('physician_id', $physicianIds)
                            ->where('custom_date', '>=', now()->toDateString())
                            ->select('physician_id', 'id', 'custom_date as date', 'lunch_start', 'lunch_end', 'lunch_enabled')
                            ->orderBy('custom_date')
                            ->get()
                            ->groupBy('physician_id');
        /* ------------------------------------
         | 5. Attach other_locations to each physician
         | This allows the frontend to show "Working in [Location]"
         | when the provider is scheduled at another location
         |-------------------------------------*/
        $physiciansWithOtherLocations = $physicians->map(function ($physician) use (
            $otherLocations,
            $department,
            $monthlyAvailabilities,
            $customLunchTimes
        ) {

            $physicianOtherLocs = $otherLocations[$physician->physician_id] ?? collect();

            $physician->other_locations = $physicianOtherLocs->map(function ($loc) {
                return [
                    'city' => $loc->physician_city,
                    'provider_code' => $loc->provider_code,
                    'physician_sun' => $loc->physician_sun,
                    'physician_mon' => $loc->physician_mon,
                    'physician_tue' => $loc->physician_tue,
                    'physician_wed' => $loc->physician_wed,
                    'physician_thu' => $loc->physician_thu,
                    'physician_fri' => $loc->physician_fri,
                    'physician_sat' => $loc->physician_sat,

                    'physician_sun_open' => $loc->physician_sun_open,
                    'physician_mon_open' => $loc->physician_mon_open,
                    'physician_tue_open' => $loc->physician_tue_open,
                    'physician_wed_open' => $loc->physician_wed_open,
                    'physician_thu_open' => $loc->physician_thu_open,
                    'physician_fri_open' => $loc->physician_fri_open,
                    'physician_sat_open' => $loc->physician_sat_open,

                    'physician_sun_close' => $loc->physician_sun_close,
                    'physician_mon_close' => $loc->physician_mon_close,
                    'physician_tue_close' => $loc->physician_tue_close,
                    'physician_wed_close' => $loc->physician_wed_close,
                    'physician_thu_close' => $loc->physician_thu_close,
                    'physician_fri_close' => $loc->physician_fri_close,
                    'physician_sat_close' => $loc->physician_sat_close,

                    'lunch_time_start' => $loc->lunch_time_start,
                    'lunch_time_end' => $loc->lunch_time_end,
                    'lunch_time_enabled' => (int) ($loc->lunch_time_enabled ?? 1),

                    'is_telemed' => (bool) $loc->is_telemed,

                    'telemed_sun' => (int) ($loc->telemed_sun ?? 0),
                    'telemed_mon' => (int) ($loc->telemed_mon ?? 0),
                    'telemed_tue' => (int) ($loc->telemed_tue ?? 0),
                    'telemed_wed' => (int) ($loc->telemed_wed ?? 0),
                    'telemed_thu' => (int) ($loc->telemed_thu ?? 0),
                    'telemed_fri' => (int) ($loc->telemed_fri ?? 0),
                    'telemed_sat' => (int) ($loc->telemed_sat ?? 0),
                    'monthly_availability' => [],
                ];
            })->values()->toArray();

            // ✅ Monthly availability (NO QUERY HERE)
            $physician->monthly_availability =
                ($physician->schedule_type === 'monthly')
                    ? ($monthlyAvailabilities[$physician->physician_id] ?? collect())->map(fn($a) => [
                        'date' => $a->date,
                        'open_time' => $a->open_time,
                        'close_time' => $a->close_time,
                        'is_telemed' => $a->is_telemed,
                    ])->values()->toArray()
                    : [];

            // ✅ Custom lunch times (NO QUERY HERE)
            $physician->custom_lunch_times =
                ($customLunchTimes[$physician->physician_id] ?? collect())->values()->toArray();

            return $physician;
        });

        /* ------------------------------------
         | 5b. Build physician-to-speciality mapping using physician_specialties pivot table
         | This ensures multi-speciality providers appear under ALL their specialities
         |-------------------------------------*/
        $physicianSpecialties = collect();
        if (!empty($physicianIds)) {
            $physicianSpecialties = PhysicianSpeciality::whereIn('physician_id', $physicianIds)
                ->select('physician_id', 'specialty')
                ->get();
        }

        // Build a map: speciality_short_name => [physician_ids]
        // First, create a map of speciality full name to short name
        $specNameToShort = $specialities->mapWithKeys(function ($spec) {
            return [$spec->name => $spec->short_name];
        })->toArray();

        // Group physicians by speciality short name using the pivot table
        // For providers WITHOUT pivot entries, fall back to the speciality_short column
        $physiciansBySpecShort = collect();
        $physiciansWithPivot = collect(); // Track which physicians have pivot entries

        foreach ($physicianSpecialties as $ps) {
            $shortName = $specNameToShort[$ps->specialty] ?? null;
            if ($shortName) {
                if (!$physiciansBySpecShort->has($shortName)) {
                    $physiciansBySpecShort[$shortName] = collect();
                }
                $physician = $physicianMap[$ps->physician_id] ?? null;
                if ($physician) {
                    $physiciansBySpecShort[$shortName]->push($physician);
                    $physiciansWithPivot->push($ps->physician_id);
                }
            }
        }

        // Fall back: physicians without pivot entries use their speciality_short column
        $physiciansWithPivotIds = $physiciansWithPivot->unique()->toArray();
        foreach ($physiciansWithOtherLocations as $physician) {
            if (!in_array($physician->physician_id, $physiciansWithPivotIds)) {
                $shortName = $physician->speciality_short;
                if ($shortName) {
                    if (!$physiciansBySpecShort->has($shortName)) {
                        $physiciansBySpecShort[$shortName] = collect();
                    }
                    $physiciansBySpecShort[$shortName]->push($physician);
                }
            }
        }

        /* ------------------------------------
         | 6. Fetch visit types with allow_per_slot for each speciality
         |-------------------------------------*/
        $specialityIds = $specialities->pluck('id')->toArray();
        $visitTypesBySpeciality = collect();
        if (!empty($specialityIds)) {
            $visitTypesBySpeciality = MedhiwaSpecialityVisitType::with(['orderType', 'duration'])
                ->whereIn('med_speciality_id', $specialityIds)
                ->whereNull('deleted_at')
                ->get()
                ->groupBy('med_speciality_id');
        }

        /* ------------------------------------
         | 7. Merge specialities with physicians and visit types
         |-------------------------------------*/
        $departmentColors = [
            'PT'         => ['primaryColor' => '#18696D', 'secondaryColor' => '#2A9D8F', 'lightBg' => '#E0F4F3'],
            'PTA'        => ['primaryColor' => '#7A6B17', 'secondaryColor' => '#9A8A2D', 'lightBg' => '#F7F4E0'],
            'OT'         => ['primaryColor' => '#8B1874', 'secondaryColor' => '#B5258E', 'lightBg' => '#FCE4F4'],
            'OTA'        => ['primaryColor' => '#8B1848', 'secondaryColor' => '#B32D66', 'lightBg' => '#FCE4ED'],
            'SLP'        => ['primaryColor' => '#1565A8', 'secondaryColor' => '#2185D0', 'lightBg' => '#E3F2FD'],
            'Psych'      => ['primaryColor' => '#5B2C8C', 'secondaryColor' => '#7B4BAB', 'lightBg' => '#F0E6F6'],
            'LPC'        => ['primaryColor' => '#1D7A4E', 'secondaryColor' => '#2E9B66', 'lightBg' => '#E2F5EC'],
            'LMSW'       => ['primaryColor' => '#8B4513', 'secondaryColor' => '#A0522D', 'lightBg' => '#F5EBE0'],
            'NPE'        => ['primaryColor' => '#2E3A8C', 'secondaryColor' => '#4355B9', 'lightBg' => '#E6E9F7'],
            'DC'         => ['primaryColor' => '#8B2318', 'secondaryColor' => '#B33A2D', 'lightBg' => '#FDECEA'],
            'PM&R'       => ['primaryColor' => '#5A7A17', 'secondaryColor' => '#7A9A2D', 'lightBg' => '#F0F5E2'],
            'Chiro Tech' => ['primaryColor' => '#6B2D8C', 'secondaryColor' => '#8B4DAB', 'lightBg' => '#F3E6F8'],
            'Neuro'      => ['primaryColor' => '#4A7A17', 'secondaryColor' => '#5E9A2D', 'lightBg' => '#EEF6E2'],
            'Nurse Prac' => ['primaryColor' => '#177A5C', 'secondaryColor' => '#2D9A76', 'lightBg' => '#E2F5EE'],
            'PA'         => ['primaryColor' => '#7A5A17', 'secondaryColor' => '#9A7A2D', 'lightBg' => '#F7F0E0'],
            'MA'         => ['primaryColor' => '#1E4A8C', 'secondaryColor' => '#3366B3', 'lightBg' => '#E4ECF8'],
            'EEGTech'    => ['primaryColor' => '#3E2E8C', 'secondaryColor' => '#5A4AB3', 'lightBg' => '#EAE6F8'],
            'PCP'        => ['primaryColor' => '#2D7A2D', 'secondaryColor' => '#4A9A4A', 'lightBg' => '#E6F5E6'],
        ];

        $finalData = $specialities->map(function ($spec) use ($physiciansBySpecShort, $visitTypesBySpeciality, $departmentColors) {
            $physicianList = $physiciansBySpecShort[$spec->short_name] ?? collect();

            $specVisitTypes = ($visitTypesBySpeciality[$spec->id] ?? collect())
                ->filter(fn($vt) => !is_null($vt->visittype_id))
                ->groupBy('visittype_id')
                ->map(function ($visitItems) {
                    $visit = $visitItems->first();
                    return [
                        'visittype_id' => $visit->visittype_id,
                        'visittype_name' => $visit->orderType->name ?? null,
                        'visittype_code' => $visit->orderType->code ?? null,
                        'allow_per_slot' => $visit->allow_per_slot,
                        'allow_multiple' => $visit->orderType->allow_multiple ?? 0,
                        'duration_slots' => $visitItems
                            ->filter(fn($item) => !is_null($item->duration_id))
                            ->unique('duration_id')
                            ->map(fn($item) => [
                                'duration_id' => $item->duration_id,
                                'duration_slot' => $item->duration->duration_slot ?? null,
                            ])
                            ->values(),
                    ];
                })
                ->values();

            return [
                'id' => $spec->id,
                'name' => $spec->name,
                'short_name' => $spec->short_name,
                'parent_id' => $spec->parent_id ?? null,
                'allow_multiple' => $spec->allow_multiple ?? 0,
                'multiple_allowed_slots' => $spec->multiple_allowed_slots ?? 0,
                'multiple_slot_duration' => $spec->multiple_slot_duration ?? 0,
                'colors' => $departmentColors[$spec->short_name] ?? [
                    'primaryColor' => '#6B7280',
                    'secondaryColor' => '#9CA3AF',
                    'lightBg' => '#F3F4F6'
                ],
                'physician_count' => $physicianList->count(),
                'physicians' => $physicianList->values(),
                'visit_types' => $specVisitTypes
            ];
        })->values();

        /* ------------------------------------
         | 7. Return JSON Response
         |-------------------------------------*/
        $responseData = [
            'success' => true,
            'count' => $finalData->count(),
            'data' => $finalData
        ];

        Log::channel('appointment')->info('Fetching department speciality with physician - Success', [
            'department' => $department,
            'speciality_count' => $finalData->count(),
            'physician_count' => $physiciansWithOtherLocations->count(),
        ]);

        return response()->json($responseData);
    }

    /**
     * GET /api/get-company-by-department-and-provider?department={city}&provider_id={id}
     *
     * Returns mapped companies for a department and provider.
     *
     * Request Payload:
     * - Query: department (required, string)
     * - Query: provider_id (required)
     *
     * Response:
     * - 200: { success: true, count: int, companies: array } or { success: false, message: string }
     * - 400: { success: false, message: string, data: [] }
     * - 500: { success: false, message: string, data: [] }
     */
    public function getCompanyByDepartmentAndProvider(Request $request){
        $department = $request->query('department');
        $providerId = $request->query('provider_id');
        Log::channel('appointment')->info('Fetching companies by department and provider - Start', [
            'department' => $department,
            'provider_id' => $providerId,
        ]);

        if (empty($department) || empty($providerId)) {
            Log::channel('appointment')->warning('Company fetch failed: missing department/provider', [
                'department' => $department,
                'provider_id' => $providerId,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Department and Provider parameters are required',
                'data' => []
            ], 400);
        }

        try {
            $companies = MedhiwaAmdProviderCompanyMapping::where('amd_location', $department)
                ->where('amd_provider_id', $providerId)
                ->get(['amd_provider_id', 'amd_code', 'amd_company_name']);

            if($companies->isEmpty()) {
                Log::channel('appointment')->info('No companies found for department/provider', [
                    'department' => $department,
                    'provider_id' => $providerId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'No companies found for this location',
                ], 200);
            }

            Log::channel('appointment')->info('Companies fetched successfully', [
                'department' => $department,
                'provider_id' => $providerId,
                'company_count' => $companies->count(),
            ]);

            return response()->json([
                'success' => true,
                'count' => $companies->count(),
                'companies' => $companies
            ], 200);
        }catch (\Throwable $e) {
            Log::channel('appointment')->error("Error fetching companies by location and department: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'data' => []
            ], 500);
        }
    }

    /**
     * POST /api/schedule-patient-appointment/{userName}/{caseId}
     *
     * Creates a new patient appointment request.
     *
     * Request Payload:
     * - department, service, physicanId, physicanName, attend_date, svc_date_start, svc_date_end, status, pa_resp, no_sessions (required)
     * - attend_type, pa_req, time, end_time, attend_notes, provider_code, company_name (optional)
     *
     * Response:
     * - 200: { success: true, message: string, appointment_id: int }
     * - 422: { success: false, message: string, errors: object }
     * - 500: { success: false, message: string, error: string }
     */
    public function schedulePatientAppointment(Request $request, $userName, $caseId){
        try{
            Log::channel('appointment')->info('Schedule appointment request started', [
                'user_name' => $userName,
                'case_id' => $caseId,
            ]);

            $validator = Validator::make($request->all(), [
                
                'department'      => 'required|string|max:100',
                'service'         => 'required|string|max:100',
                'attend_type'     => 'nullable|string|max:10',
                'pa_req'          => 'nullable|string|max:10',

                'physicanId'      => 'required|integer',
                'physicanName'    => 'required|string|max:255',

                'attend_date'     => 'required|date',
                'svc_date_start'  => 'required|date|before_or_equal:svc_date_end',
                'svc_date_end'    => 'required|date|after_or_equal:svc_date_start',

                'time'            => 'nullable|date_format:H:i',
                'end_time'        => 'nullable|date_format:H:i|after:time',

                'status'          => 'required|string|max:50',
                'pa_resp'         => 'required|string|max:50',

                'attend_notes'    => 'nullable|string',

                'no_sessions'     => 'required|integer|min:1',

                'provider_code'   => 'nullable|string|max:50',
                'company_name'    => 'nullable|string|max:255',
            
            ]);

            if ($validator->fails()) {
                Log::channel('appointment')->warning('Schedule appointment validation failed', [
                    'user_name' => $userName,
                    'case_id' => $caseId,
                    'errors' => $validator->errors()->toArray(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }
            

            // Log the incoming request data
            Log::channel('appointment')->info("Scheduling appointment for user: $userName, case: $caseId", [
                'request_data' => $request->all()
            ]);

            $start_time = date('H:i:s', strtotime($request->input('time')));
            $end_time = date('H:i:s', strtotime($request->input('end_time')));

            $start = Carbon::parse($start_time);
            $end = Carbon::parse($end_time);
            $duration = $start->diffInMinutes($end);

            $diff = $end->diff($start);
            $hours = $diff->h;
            $minutes = $diff->i / 60;

            $pixels = ($hours * 92 + $minutes * 92) . 'px';

            $appointment = MedhiwaAttendance::create([
                'username' => $userName,
                'ma_id' => 1,
                'department' => $request->input('department'),
                'service' => $request->input('service'),
                'attend_type' => $request->input('attend_type', null),
                'pa_req' => $request->input('pa_req', null),
                'provider_id' => $request->input('physicanId'),
                'provider_name' => $request->input('physicanName'),
                'attend_date' => $request->input('attend_date'),
                'time' => $request->input('time', null),
                'end_time' => $request->input('end_time', null),
                'attend_status' =>  'Requested' ?? $request->input('attend_status') ,
                'attend_notes' => $request->input('attend_notes', null),
                'provider_code' => $request->input('provider_code', null),
                'company_name' => $request->input('company_name', null),
                'pixels' => $pixels,
                'length' => $duration,
                'platform_name' => 'New Patient',
            ]);

            Log::channel('appointment')->info('Schedule appointment request completed', [
                'appointment_id' => $appointment->id,
                'user_name' => $userName,
                'case_id' => $caseId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Appointment request submitted successfully',
                'appointment_id' => $appointment->id
            ],200);

        }catch(\Throwable $e){
            Log::channel('appointment')->error("Error scheduling patient appointment: " . $e->getMessage());
            return response()->json([
                'success' => false,
                // 'error' => $e->getMessage(),
                'message' => 'Something went wrong',
            ], 500);
        }
    }


    /**
     * Return start-time and (optionally) end-time options that match the scheduling popup dropdowns.
     *
     * GET /api/available-time-slots
     *   ?provider_id=  (required)
     *   &date=         (required, YYYY-MM-DD)
     *   &location=     (required)
     *   &start_time=   (optional, H:i – when supplied, end_times array is also returned)
     *
     * Slot labels mirror the reschedule/schedule modal JS:
     *   available           → "08:00 am"
     *   lunch               → "11:00 am (Lunch)"           disabled
     *   booked same loc     → "08:00 am (Booked)"          disabled
     *   booked cross-loc    → "08:00 am (Booked for X)"    disabled
     *   booked cross telemed→ "08:00 am (Booked for X – Telemed)" disabled
     */
    public function getAvailableTimeSlots(Request $request)
    {
        $caseId = $request->query('case_id');

        if (empty($caseId)) {
            return response()->json(['status' => false, 'message' => 'Case ID is required.'], 422);
        }

        $patientIds = auth()->user()->getActivePatientIds();
        $caseRecord = AhcsCase::where('id', $caseId)->whereIn('patient_id', $patientIds)->first(['patient_id']);

        if (!$caseRecord) {
            return response()->json(['status' => false, 'message' => 'Invalid Case ID for this patient.'], 422);
        }

        $providerId = $request->query('provider_id');
        $date       = $request->query('date');
        $location   = $request->query('location');
        $startTime  = $request->query('start_time');

        $params = array_filter([
            'provider_id' => $providerId,
            'date'        => $date,
            'location'    => $location,
            'start_time'  => $startTime,
        ], fn($v) => $v !== null);

        $url = config('services.app_server.api_url') . '/available-time-slots?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            Log::error('getAvailableTimeSlots curl error: ' . $curlErr);
            return response()->json([
                'status'  => false,
                'message' => 'Failed to reach availability service.',
                'error'   => $curlErr,
            ], 502);
        }

        $data = json_decode($body, true);

        return response()->json($data ?? [], $httpCode ?: 500);
    }

    public function checkSessionsCompleted(Request $request)
    {
        $caseId = $request->query('case_id');

        if (empty($caseId)) {
            return response()->json(['status' => false, 'message' => 'Case ID is required.'], 422);
        }

        $patientIds = auth()->user()->getActivePatientIds();
        $caseRecord = AhcsCase::where('id', $caseId)->whereIn('patient_id', $patientIds)->first(['patient_id']);

        if (!$caseRecord) {
            return response()->json(['status' => false, 'message' => 'Invalid Case ID for this patient.'], 422);
        }

        $maId = $request->query('ma_id');

        $params = array_filter([
            'case_id' => $caseId,
            'ma_id'   => $maId,
        ], fn($v) => $v !== null);

        $url = config('services.app_server.api_url') . '/preauth/check-sessions-completed?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            Log::error('checkSessionsCompleted curl error: ' . $curlErr);
            return response()->json([
                'status'  => false,
                'message' => 'Failed to reach sessions service.',
                'error'   => $curlErr,
            ], 502);
        }

        $data = json_decode($body, true);

        return response()->json($data ?? [], $httpCode ?: 500);
    }

    public function getApprovedPreauth(Request $request)
    {
        $caseId = $request->query('case_id');

        if (empty($caseId)) {
            return response()->json(['status' => false, 'message' => 'Case ID is required.'], 422);
        }

        $patientIds = auth()->user()->getActivePatientIds();
        $caseRecord = AhcsCase::where('id', $caseId)->whereIn('patient_id', $patientIds)->first(['patient_id']);

        if (!$caseRecord) {
            return response()->json(['status' => false, 'message' => 'Invalid Case ID for this patient.'], 422);
        }

        $url = config('services.app_server.api_url') . '/preauth/get-approved-preauth'
            . ($caseId ? '?' . http_build_query(['case_id' => $caseId]) : '');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            Log::error('getApprovedPreauth curl error: ' . $curlErr);
            return response()->json([
                'status'  => false,
                'message' => 'Failed to reach preauth service.',
                'error'   => $curlErr,
            ], 502);
        }

        $data = json_decode($body, true);

        return response()->json($data ?? [], $httpCode ?: 500);
    }

    public function getTimeSlotDateRange(Request $request)
    {
        $caseId = $request->query('case_id');

        if (empty($caseId)) {
            return response()->json(['status' => false, 'message' => 'Case ID is required.'], 422);
        }

        $patientIds = auth()->user()->getActivePatientIds();
        $caseRecord = AhcsCase::where('id', $caseId)->whereIn('patient_id', $patientIds)->first(['patient_id']);

        if (!$caseRecord) {
            return response()->json(['status' => false, 'message' => 'Invalid Case ID for this patient.'], 422);
        }

        $params = array_filter([
            'provider_id' => $request->query('provider_id'),
            'svc_date_start'  => $request->query('svc_date_start'),
            'svc_date_end'    => $request->query('svc_date_end'),
            'location'    => $request->query('location'),
        ], fn($v) => $v !== null);

        $url = config('services.app_server.api_url') . '/get-time-slots-date-range?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            Log::error('getTimeSlotDateRange curl error: ' . $curlErr);
            return response()->json([
                'status'  => false,
                'message' => 'Failed to reach time slots service.',
                'error'   => $curlErr,
            ], 502);
        }

        $data = json_decode($body, true);

        return response()->json($data ?? [], $httpCode ?: 500);
    }

    public function getAppointmentReasons(Request $request)
    {
        try {
            Log::channel('appointment')->info('Get Appointment Reasons API hit', [
                'user_id' => auth()->id()
            ]);

            $caseId = $request->query('case_id');

            if (empty($caseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case ID is required.',
                ], 422);
            }

            $patientIds = auth()->user()->getActivePatientIds();

            $caseRecord = AhcsCase::where('id', $caseId)
                ->whereIn('patient_id', $patientIds)
                ->first(['patient_id']);

            if (!$caseRecord) {
                Log::channel('appointment')->warning('Invalid case ID for user', [
                    'user_id' => auth()->id(),
                    'case_id' => $caseId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Case ID for this patient.',
                ], 422);
            }

            Log::channel('appointment')->info('Case validated', [
                'case_id'    => $caseId,
                'patient_id' => $caseRecord->patient_id,
            ]);

            $url = config('services.app_server.api_url') . '/get-appointment-reasons';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            ]);

            $body     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                Log::channel('appointment')->error('getAppointmentReasons curl error: ' . $curlErr);
                return response()->json([
                    'status'  => false,
                    'message' => 'Failed to reach appointment reasons service.',
                    'error'   => $curlErr,
                ], 502);
            }

            $data = json_decode($body, true);

            Log::channel('appointment')->info('Appointment reasons fetched successfully', [
                'http_code' => $httpCode,
            ]);

            return response()->json($data ?? [], $httpCode ?: 500);

        } catch (\Throwable $e) {
            Log::channel('appointment')->error('Error fetching appointment reasons: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                // 'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getAppointment(Request $request){
        try{
            Log::channel('appointment')->info('Get Appointment API hit', [
                'user_id' => auth()->id()
            ]);

            $appId  = $request->query('appt_id');
            $caseId = $request->query('case_id');

            if(empty($appId)){
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment ID is required',
                ], 422);
            }

            if(empty($caseId)){
                return response()->json([
                    'success' => false,
                    'message' => 'Case ID is required.',
                ], 422);
            }

            $userDetails = auth()->user();
            $patientIds  = $userDetails->getActivePatientIds();

            $caseRecord = AhcsCase::where('id', $caseId)
                ->whereIn('patient_id', $patientIds)
                ->first(['patient_id']);

            if(!$caseRecord){
                Log::channel('appointment')->warning('Invalid case ID for user', [
                    'user_id' => auth()->id(),
                    'case_id' => $caseId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Case ID for this patient.',
                ], 422);
            }

            Log::channel('appointment')->info('Case validated', [
                'case_id'    => $caseId,
                'patient_id' => $caseRecord->patient_id,
            ]);

            $apptDetails = AhcsAttendance::where('id', $appId)->get([
                    'id','ma_id','department','service','attend_type',
                    'provider_id','provider_name','attend_date','time',
                    'end_time','length','attend_status','attend_notes','is_virtual','transport'
                ]);

            if($apptDetails->isEmpty()){
                Log::channel('appointment')->warning('No appointment found', [
                    'appt_id' => $appId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'No appointment found for the given ID',
                ], 404);
            }

            Log::channel('appointment')->info('Appointment fetched successfully', [
                'appt_id'  => $appId,
                'case_id' => $caseId,
            ]);

            return response()->json([
                'success' => true,
                'data' => $apptDetails
            ], 200);


        }catch(\Throwable $e){
            Log::channel('appointment')->error('Error fetching appointment: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
            ]);
            return response()->json([
                'success' => false,
                // 'error' => $e->getMessage(),
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    public function appointmentReschedule(Request $request)
    {
        try {
            Log::channel('appointment')->info('Appointment Reschedule API hit', [
                'user_id' => auth()->id()
            ]);

            $caseId = $request->query('case_id');
            $appId  = $request->query('appt_id');

            if (empty($caseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case ID is required.',
                ], 422);
            }

            if (empty($appId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment ID is required.',
                ], 422);
            }

            $maId = $request->query('ma_id');

            if (empty($maId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Med Auth ID is required.',
                ], 422);
            }

            $patientIds = auth()->user()->getActivePatientIds();

            $caseRecord = AhcsCase::where('id', $caseId)
                ->whereIn('patient_id', $patientIds)
                ->first(['patient_id']);

            if (!$caseRecord) {
                Log::channel('appointment')->warning('Invalid case ID for user', [
                    'user_id' => auth()->id(),
                    'case_id' => $caseId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Case ID for this patient.',
                ], 422);
            }

            Log::channel('appointment')->info('Case validated', [
                'case_id'    => $caseId,
                'patient_id' => $caseRecord->patient_id,
            ]);

            $medAuth = AhcsMedAuth::where('id', $maId)
                ->where('case_id', $caseId)
                ->first(['id', 'status', 'no_sessions', 'sessions_completed']);

            if (!$medAuth) {
                Log::channel('appointment')->warning('Med auth not found for case', [
                    'ma_id'   => $maId,
                    'case_id' => $caseId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'No med auth found for the given case.',
                ], 404);
            }

            if ($medAuth->status !== 'Approved') {
                Log::channel('appointment')->warning('Med auth status not approved', [
                    'ma_id'   => $maId,
                    'case_id' => $caseId,
                    'status'  => $medAuth->status,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Med auth is not approved.',
                ], 422);
            }

            if ($medAuth->no_sessions <= 0) {
                Log::channel('appointment')->warning('Med auth has no remaining sessions', [
                    'ma_id'       => $maId,
                    'case_id'     => $caseId,
                    'no_sessions' => $medAuth->no_sessions,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Med auth has no remaining sessions.',
                ], 422);
            }

            if ($medAuth->sessions_completed >= $medAuth->no_sessions) {
                Log::channel('appointment')->warning('Med auth sessions already completed', [
                    'ma_id'              => $maId,
                    'case_id'            => $caseId,
                    'no_sessions'        => $medAuth->no_sessions,
                    'sessions_completed' => $medAuth->sessions_completed,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'All authorized sessions have already been completed.',
                ], 422);
            }

            Log::channel('appointment')->info('Med auth validated', [
                'ma_id'              => $maId,
                'case_id'            => $caseId,
                'status'             => $medAuth->status,
                'no_sessions'        => $medAuth->no_sessions,
                'sessions_completed' => $medAuth->sessions_completed,
            ]);

            $appointment = AhcsAttendance::where('id', $appId)
                ->where('ma_id', $maId)
                ->first(['id', 'attend_date', 'time']);

            if (!$appointment) {
                Log::channel('appointment')->warning('Appointment not found for reschedule', [
                    'appt_id' => $appId,
                    'ma_id'   => $maId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment not found.',
                ], 404);
            }

            $appointmentDateTime = Carbon::parse($appointment->attend_date . ' ' . $appointment->time);

            if ($appointmentDateTime->lessThanOrEqualTo(now()->addHours(24))) {
                Log::channel('appointment')->warning('Reschedule attempted within 24 hours of appointment', [
                    'appt_id'      => $appId,
                    'attend_date'  => $appointment->attend_date,
                    'time'         => $appointment->time,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment can only be rescheduled at least 24 hours before the scheduled time.',
                ], 422);
            }

            $userName = auth()->user()->name;
            $payload  = $request->all();

            Log::channel('appointment')->info('Sending reschedule request', [
                'user_name' => $userName,
                'case_id'   => $caseId,
                'appt_id'    => $appId,
                'payload'   => $payload,
            ]);

            $url = config('services.app_server.api_url')
                . '/patient-portal-physician-update-appt-schedule/' . urlencode($userName) . '/' . $caseId . '/' . $appId;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                ],
            ]);

            $body     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                Log::channel('appointment')->error('appointmentReschedule curl error: ' . $curlErr, [
                    'user_name' => $userName,
                    'case_id'   => $caseId,
                    'appt_id'    => $appId,
                ]);
                return response()->json([
                    'status'  => false,
                    'message' => 'Failed to reach reschedule service.',
                    'error'   => $curlErr,
                ], 502);
            }

            $data = json_decode($body, true);

            Log::channel('appointment')->info('Appointment rescheduled successfully', [
                'user_name'  => $userName,
                'case_id'    => $caseId,
                'appt_id'     => $appId,
                'http_code'  => $httpCode,
            ]);

            return response()->json($data ?? [], $httpCode ?: 500);

        } catch (\Throwable $e) {
            Log::channel('appointment')->error('Error rescheduling appointment: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                // 'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function notifyPatientPreauthMissingDetails(Request $request)
    {
        try {
            Log::channel('appointment')->info('Notify patient preauth missing details API hit', [
                'user_id' => auth()->id()
            ]);

            $caseId = $request->query('case_id');
            $ma_id = $request->query('ma_id');

            if (empty($caseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case ID is required.',
                ], 422);
            }

            $patientIds = auth()->user()->getActivePatientIds();

            $caseRecord = AhcsCase::where('id', $caseId)
                ->whereIn('patient_id', $patientIds)
                ->first(['patient_id']);

            if (!$caseRecord) {
                Log::channel('appointment')->warning('Invalid case ID for user', [
                    'user_id' => auth()->id(),
                    'case_id' => $caseId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Case ID for this patient.',
                ], 422);
            }

            Log::channel('appointment')->info('Case validated', [
                'case_id'    => $caseId,
                'patient_id' => $caseRecord->patient_id,
            ]);

            $maDetails = AhcsMedAuth::where('id', $ma_id)
                ->where('case_id', $caseId)
                ->first();

            if (!$maDetails) {
                Log::channel('appointment')->warning('Med auth not found for case', [
                    'ma_id'   => $ma_id,
                    'case_id' => $caseId,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Medauth Id Not Found',
                ], 404);
            }

            Log::channel('appointment')->info('Patient notified for preauth missing details', [
                'case_id'    => $caseId,
                'patient_id' => $caseRecord->patient_id,
                'ma_id'      => $ma_id,
            ]);

            PatientPortalPreauthMissingDetail::create([
                'case_id' => $caseId,
                'patient_id' => $caseRecord->patient_id,
                'ma_id' => $ma_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Patient notified for preauth missing details successfully',
            ], 200);

        }catch(\Throwable $e) {
            Log::channel('appointment')->error('Error notifying patient preauth missing details: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                // 'error'   => $e->getMessage(),
            ], 500);
        }
    }
            
}
