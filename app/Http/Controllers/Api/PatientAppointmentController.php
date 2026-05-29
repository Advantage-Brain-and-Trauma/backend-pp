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
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PatientAppointmentController extends Controller
{
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
            $user = auth()->user();
            $patientId = $user->patient_id;
            $caseId = $request->input('case_id');

            if (!$patientId) {
                throw new \Exception("Patient ID is required", 400);
            }

            if (empty($caseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case Id is required',
                ], 422);
            }

            // ✅ Check patient exists
            AhcsPatient::findOrFail($patientId);
            Log::channel('appointment')->info('Patient found', ['patient_id' => $patientId]);

            $isValidCaseForPatient = AhcsCase::where('id', $caseId)
                ->where('patient_id', $patientId)
                ->exists();

            if (!$isValidCaseForPatient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Case Id for this patient',
                ], 422);
            }

            $caseQuery = AhcsCase::where('patient_id', $patientId);

            if (!empty($caseId)) {
                $caseQuery->where('id', $caseId);
            }

            $caseIds = $caseQuery->pluck('id');

            if ($caseIds->isEmpty()) {
                throw new \Exception("No cases found for this patient", 404);
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
                    'end_time','length','attend_status','attend_notes','is_virtual'
                ]);
            Log::channel('appointment')->info('Appointments fetched', ['patient_id' => $patientId, 'appointment_count' => $appointments->count()]);

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
            $appointments->transform(function ($appointment) use ($specialities, $attendTypes) {
                $appointment->service_full_name = $specialities[$appointment->service] ?? null;
                $code = strtolower($appointment->attend_type);
                $appointment->attend_type_full_name = $attendTypes[$code] ?? null;

                $appointment->is_virtual_text = $appointment->is_virtual == 1
                    ? 'Telehealth'
                    : 'In-Person';

                $appointment->appt_status = 'Confirmed';

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
                                ->select('provider_id', 'available_date as date', 'open_time', 'close_time')
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

                    'telemed_sun' => (int) ($loc->telemed_sun ?? 1),
                    'telemed_mon' => (int) ($loc->telemed_mon ?? 1),
                    'telemed_tue' => (int) ($loc->telemed_tue ?? 1),
                    'telemed_wed' => (int) ($loc->telemed_wed ?? 1),
                    'telemed_thu' => (int) ($loc->telemed_thu ?? 1),
                    'telemed_fri' => (int) ($loc->telemed_fri ?? 1),
                    'telemed_sat' => (int) ($loc->telemed_sat ?? 1),
                ];
            })->values()->toArray();

            // ✅ Monthly availability (NO QUERY HERE)
            $physician->monthly_availability =
                ($physician->schedule_type === 'monthly')
                    ? ($monthlyAvailabilities[$physician->physician_id] ?? collect())->values()->toArray()
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
        $finalData = $specialities->map(function ($spec) use ($physiciansBySpecShort, $visitTypesBySpeciality) {
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
                'error' => $e->getMessage(),
                'message' => 'Something went wrong',
            ], 500);
        }
    }
}
