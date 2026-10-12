<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AhcsAppointmentSchedulerResponse;
use App\Models\AhcsAttendance;
use App\Models\AhcsCase;
use App\Models\AhcsMedAuth;
use App\Models\AhcsPatient;
use App\Models\AhcsShortUrl;
use App\Models\MedhiwaCareNewOrderType;
use App\Models\MedhiwaSpecialityLocation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Patient side of the appointment reminder SMS link.
 *
 * Medhiwa sends the reminder and mints the token (ahcs.ahcs_short_urls: token -> base64 JSON
 * of appointment_id / patient_id / case_id / cell_no). These routes only HANDLE the link: they
 * are public, so the token is the only credential and every call is scoped to the one
 * appointment it names.
 *
 * Replies are written to ahcs.ahcs_appointment_scheduler_response, the same table the old
 * portal page (10.0.0.9 SmsController) wrote and Medhiwa already reads.
 */
class AppointmentReminderController extends Controller
{
    private const TIMEZONE = 'America/Chicago';

    // patient_apt_msg_status as Medhiwa reads it: 1 = Approved, 0 = Denied (with a reason).
    private const STATUS_CONFIRMED = 1;

    private const HELP_CATEGORIES = [
        'transportation' => [
            'label'   => 'Transportation help',
            'options' => [
                'ride' => 'I need help finding a ride',
                'call' => 'Please call me about transportation',
            ],
        ],
        'location' => [
            'label'   => 'Location question',
            'options' => [
                'parking'       => 'Parking information',
                'entrance'      => 'Which entrance to use',
                'accessibility' => 'Accessibility information',
                'call'          => 'Please call me about the location',
            ],
        ],
        'other' => [
            'label'   => 'Another concern',
            'options' => [
                'insurance'  => 'Insurance or cost',
                'paperwork'  => 'Paperwork questions',
                'scheduling' => 'Another scheduling question',
                'other'      => 'Something else',
            ],
        ],
    ];

    /**
     * GET /api/appointment-reminder/{token}
     *
     * Response:
     * - 200: { success: true, data: { patient_first_name, attend_date, time, end_time, provider_name,
     *          service, visit_type, visit_type_name, location, address, facility_phone_no, is_virtual,
     *          attend_status, can_respond, response: { status, reason } } }
     * - 404: { success: false, message: string }  unknown token
     * - 410: { success: false, message: string }  appointment already passed
     */
    public function show(string $token): JsonResponse
    {
        return $this->handle($token, 'show', function (array $reminder) {
            return response()->json([
                'success' => true,
                'data'    => $this->appointmentPayload($reminder),
            ], 200);
        });
    }

    /**
     * POST /api/appointment-reminder/{token}/confirm
     *
     * Response:
     * - 200: { success: true, message: string, data: { ...same as show } }
     * - 422: { success: false, message: string }  appointment is no longer scheduled
     */
    public function confirm(string $token): JsonResponse
    {
        return $this->handle($token, 'confirm', function (array $reminder) {
            if ($notScheduled = $this->rejectIfNotScheduled($reminder)) {
                return $notScheduled;
            }

            $latest = $this->latestResponse($reminder);

            if (!$latest || (int) $latest->patient_apt_msg_status !== self::STATUS_CONFIRMED) {
                $this->saveResponse($reminder, self::STATUS_CONFIRMED, '');
            }

            return response()->json([
                'success' => true,
                'message' => 'Appointment confirmed.',
                'data'    => $this->appointmentPayload($reminder),
            ], 200);
        });
    }

    /**
     * POST /api/appointment-reminder/{token}/help-request
     *
     * The appointment stays as it is; staff follow up with the patient.
     *
     * Request Payload:
     * - category (required: transportation | location | other)
     * - option (required, one of the category's options — see HELP_CATEGORIES)
     * - note (optional string, max 500; required when category is "other")
     *
     * Response:
     * - 200: { success: true, message: string, data: { request: string, ...same as show } }
     * - 422: { success: false, message: 'Validation errors', errors: object }
     */
    public function helpRequest(Request $request, string $token): JsonResponse
    {
        return $this->handle($token, 'help-request', function (array $reminder) use ($request) {
            if ($notScheduled = $this->rejectIfNotScheduled($reminder)) {
                return $notScheduled;
            }

            $category = (string) $request->input('category');

            $validator = Validator::make($request->all(), [
                'category' => ['required', Rule::in(array_keys(self::HELP_CATEGORIES))],
                'option'   => ['required', Rule::in(array_keys(self::HELP_CATEGORIES[$category]['options'] ?? []))],
                'note'     => [Rule::requiredIf($category === 'other'), 'nullable', 'string', 'max:500'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $optionLabel = self::HELP_CATEGORIES[$category]['options'][$request->input('option')];
            $note        = trim((string) $request->input('note'));
            $reason      = self::HELP_CATEGORIES[$category]['label'] . ': ' . $optionLabel
                . ($note !== '' ? ' - ' . $note : '');

            $helpStatus = (int) config('services.appointment_reminder.help_status');
            $latest     = $this->latestResponse($reminder);

            // A double tap or a page refresh must not stack identical rows for staff.
            $isRepeat = $latest
                && (int) $latest->patient_apt_msg_status === $helpStatus
                && (string) $latest->patient_apt_msg_reason === $reason;

            if (!$isRepeat) {
                $this->saveResponse($reminder, $helpStatus, $reason);
            }

            return response()->json([
                'success' => true,
                'message' => 'Help request received.',
                'data'    => array_merge($this->appointmentPayload($reminder), ['request' => $optionLabel]),
            ], 200);
        });
    }

    /**
     * GET /api/appointment-reminder/{token}/time-slots
     *
     * Open times later the same day, with the same provider and location.
     *
     * Response:
     * - 200: { success: true, data: { attend_date, current_time, slots: [{ time: 'H:i', label: 'g:i A' }] } }
     * - 502: { success: false, message: string }
     */
    public function timeSlots(string $token): JsonResponse
    {
        return $this->handle($token, 'time-slots', function (array $reminder) {
            if ($notScheduled = $this->rejectIfNotScheduled($reminder)) {
                return $notScheduled;
            }

            $slots = $this->availableSlots($reminder);

            if ($slots instanceof JsonResponse) {
                return $slots;
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'attend_date'  => $reminder['appointment']->attend_date,
                    'current_time' => $reminder['appointment']->time,
                    'slots'        => $slots,
                ],
            ], 200);
        });
    }

    /**
     * POST /api/appointment-reminder/{token}/reschedule
     *
     * Moves the appointment to another open time on the same day.
     *
     * Request Payload:
     * - time (required, 'H:i' — must be one of the slots returned by time-slots)
     *
     * Response:
     * - 200: { success: true, message: string, data: { attend_date, time, end_time } }
     * - 422: { success: false, message: string }
     * - 4xx/5xx: { success: false, message: string }  message from the scheduling service
     */
    public function reschedule(Request $request, string $token): JsonResponse
    {
        return $this->handle($token, 'reschedule', function (array $reminder) use ($request) {
            if ($notScheduled = $this->rejectIfNotScheduled($reminder)) {
                return $notScheduled;
            }

            $validator = Validator::make($request->all(), [
                'time' => 'required|date_format:H:i',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $appointment = $reminder['appointment'];
            $time        = $request->input('time');

            // Same med-auth rules the logged-in portal applies in appointmentReschedule().
            $medAuth = AhcsMedAuth::where('id', $appointment->ma_id)
                ->first(['id', 'status', 'no_sessions', 'sessions_completed']);

            if (!$medAuth || $medAuth->status !== 'Approved'
                || $medAuth->no_sessions <= 0
                || $medAuth->sessions_completed >= $medAuth->no_sessions) {
                return response()->json([
                    'success' => false,
                    'message' => 'This appointment cannot be rescheduled online. Please call us to reschedule.',
                ], 422);
            }

            $slots = $this->availableSlots($reminder);

            if ($slots instanceof JsonResponse) {
                return $slots;
            }

            if (!in_array($time, array_column($slots, 'time'), true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'That time is no longer available. Please choose another time.',
                ], 422);
            }

            $url = config('services.app_server.api_url')
                . '/patient-portal/physician-update-appt-schedule/'
                . rawurlencode($this->patientName($reminder['patient_id'])) . '/' . $reminder['case_id'] . '/' . $appointment->id;

            $response = Http::acceptJson()->timeout(90)->post($url, [
                'attend_date'   => $appointment->attend_date,
                'time'          => $time,
                'attend_status' => 'RS',
                'reason'        => 'Rescheduled by patient from appointment reminder link',
            ]);

            $data = $response->json() ?? [];

            if (!$response->successful() || ($data['status'] ?? null) !== 'success') {
                Log::channel('appointment')->warning('Appointment reminder reschedule rejected', [
                    'appt_id'   => $appointment->id,
                    'http_code' => $response->status(),
                    'response'  => $data,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $data['message'] ?? $data['error'] ?? 'Failed to reschedule the appointment.',
                ], $response->successful() ? 422 : $response->status());
            }

            // The reschedule replaces the appointment row. The patient picked this time themselves,
            // so the new appointment is recorded as confirmed and is not chased for a reply again.
            $newAppointment = !empty($data['new_attend_id'])
                ? AhcsAttendance::where('id', $data['new_attend_id'])->first(['id', 'attend_date', 'time', 'end_time'])
                : null;

            if ($newAppointment) {
                $this->saveResponse(array_merge($reminder, ['appointment' => $newAppointment]), self::STATUS_CONFIRMED, '');
            }

            return response()->json([
                'success' => true,
                'message' => 'Appointment rescheduled successfully.',
                'data'    => [
                    'attend_date' => $newAppointment->attend_date ?? $appointment->attend_date,
                    'time'        => $newAppointment->time ?? Carbon::createFromFormat('H:i', $time)->format('H:i:s'),
                    'end_time'    => $newAppointment->end_time ?? null,
                ],
            ], 200);
        });
    }

    /**
     * Resolve the token, run the action, and keep failures from leaking details.
     */
    private function handle(string $token, string $action, callable $callback): JsonResponse
    {
        try {
            $reminder = $this->resolveReminder($token);

            if ($reminder instanceof JsonResponse) {
                return $reminder;
            }

            Log::channel('appointment')->info('Appointment reminder link ' . $action, [
                'appt_id'    => $reminder['appointment']->id,
                'case_id'    => $reminder['case_id'],
                'patient_id' => $reminder['patient_id'],
            ]);

            return $callback($reminder);
        } catch (\Throwable $e) {
            Log::channel('appointment')->error('Appointment reminder link ' . $action . ' failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    /**
     * @return array{appointment: AhcsAttendance, patient_id: int, case_id: int}|JsonResponse
     */
    private function resolveReminder(string $token)
    {
        $invalid = response()->json([
            'success' => false,
            'message' => 'This link is not valid.',
        ], 404);

        $shortUrl = AhcsShortUrl::where('token', $token)->first(['long_url']);

        if (!$shortUrl) {
            return $invalid;
        }

        $payload       = json_decode((string) base64_decode((string) $shortUrl->long_url, true), true);
        $appointmentId = (int) ($payload['appointment_id'] ?? 0);
        $patientId     = (int) ($payload['patient_id'] ?? 0);
        $caseId        = (int) ($payload['case_id'] ?? 0);

        if (!$appointmentId || !$patientId || !$caseId) {
            return $invalid;
        }

        $appointment = AhcsAttendance::where('id', $appointmentId)->first([
            'id', 'ma_id', 'department', 'service', 'attend_type', 'provider_id', 'provider_name',
            'attend_date', 'time', 'end_time', 'attend_status', 'is_virtual',
        ]);

        // The payload must describe one consistent patient -> case -> appointment chain.
        if (!$appointment
            || !AhcsCase::where('id', $caseId)->where('patient_id', $patientId)->exists()
            || !AhcsMedAuth::where('id', $appointment->ma_id)->where('case_id', $caseId)->exists()) {
            return $invalid;
        }

        if (Carbon::parse($appointment->attend_date . ' ' . $appointment->time, self::TIMEZONE)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'This appointment has already passed.',
            ], 410);
        }

        return [
            'appointment' => $appointment,
            'patient_id'  => $patientId,
            'case_id'     => $caseId,
        ];
    }

    private function appointmentPayload(array $reminder): array
    {
        $appointment = $reminder['appointment'];

        $location = !empty($appointment->department)
            ? MedhiwaSpecialityLocation::whereRaw('LOWER(city) = ?', [strtolower($appointment->department)])->first()
            : null;

        $visitTypeName = !empty($appointment->attend_type)
            ? MedhiwaCareNewOrderType::whereRaw('LOWER(code) = ?', [strtolower($appointment->attend_type)])->value('name')
            : null;

        $latest = $this->latestResponse($reminder);
        $status = null;

        if ($latest) {
            $status = (int) $latest->patient_apt_msg_status === self::STATUS_CONFIRMED ? 'confirmed' : 'help_requested';
        }

        return [
            'patient_first_name' => AhcsPatient::where('id', $reminder['patient_id'])->value('first_name'),
            'attend_date'        => $appointment->attend_date,
            'time'               => $appointment->time,
            'end_time'           => $appointment->end_time,
            'provider_name'      => $appointment->provider_name,
            'service'            => $appointment->service,
            'visit_type'         => $appointment->attend_type,
            'visit_type_name'    => $visitTypeName,
            'location'           => $appointment->department,
            'address'            => $location->address ?? null,
            'facility_phone_no'  => $location->location_phone_no ?? null,
            'is_virtual'         => $appointment->is_virtual,
            'attend_status'      => $appointment->attend_status,
            'can_respond'        => $appointment->attend_status === 'S',
            'response'           => [
                'status' => $status,
                'reason' => $latest->patient_apt_msg_reason ?? null,
            ],
        ];
    }

    private function rejectIfNotScheduled(array $reminder): ?JsonResponse
    {
        if ($reminder['appointment']->attend_status === 'S') {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'This appointment is no longer scheduled. Please call us for help.',
        ], 422);
    }

    private function latestResponse(array $reminder): ?AhcsAppointmentSchedulerResponse
    {
        return AhcsAppointmentSchedulerResponse::where('patient_id', $reminder['patient_id'])
            ->where('case_id', $reminder['case_id'])
            ->where('appointment_id', $reminder['appointment']->id)
            ->orderByDesc('id')
            ->first();
    }

    private function saveResponse(array $reminder, int $status, string $reason): void
    {
        AhcsAppointmentSchedulerResponse::create([
            'patient_id'             => $reminder['patient_id'],
            'case_id'                => $reminder['case_id'],
            'appointment_id'         => $reminder['appointment']->id,
            'patient_apt_msg_status' => $status,
            'patient_apt_msg_reason' => $reason,
        ]);
    }

    private function patientName(int $patientId): string
    {
        $patient = AhcsPatient::where('id', $patientId)->first(['first_name', 'last_name']);
        $name    = trim(($patient->first_name ?? '') . ' ' . ($patient->last_name ?? ''));

        return $name !== '' ? $name : 'Patient';
    }

    /**
     * Bookable start times later the same day, from the scheduling service.
     *
     * @return array<int, array{time: string, label: string}>|JsonResponse
     */
    private function availableSlots(array $reminder)
    {
        $appointment = $reminder['appointment'];

        $response = Http::acceptJson()->timeout(90)->get(
            config('services.app_server.api_url') . '/patient-portal/available-time-slots',
            array_filter([
                'case_id'     => $reminder['case_id'],
                'provider_id' => $appointment->provider_id,
                'location'    => $appointment->department,
                'service'     => $appointment->service,
                'visit_type'  => $appointment->attend_type,
                'date'        => $appointment->attend_date,
            ], fn ($value) => $value !== null)
        );

        if ($response->failed()) {
            Log::channel('appointment')->error('Appointment reminder time slots request failed', [
                'appt_id'   => $appointment->id,
                'http_code' => $response->status(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to reach availability service.',
            ], 502);
        }

        $currentTime = Carbon::parse($appointment->time)->format('H:i');
        $slots       = [];

        foreach ($response->json('start_times') ?? [] as $slot) {
            $value = Carbon::parse($slot['value'] ?? '')->format('H:i');

            if (empty($slot['value']) || !empty($slot['disabled']) || $value <= $currentTime) {
                continue;
            }

            $slots[] = [
                'time'  => $value,
                'label' => Carbon::parse($value)->format('g:i A'),
            ];
        }

        return $slots;
    }
}
