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
use App\Services\AppointmentReminderChatService;
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

    // The reschedule screen offers a short list, not the provider's whole day.
    private const MAX_SLOTS_SHOWN = 3;

    private ?string $noSlotsReason = null;

    // These visits may only start on the hour (8:00, 9:00 ...), never at :15, :30 or :45.
    private const ON_THE_HOUR_VISIT_TYPES = ['IC'];
    private const ON_THE_HOUR_SERVICES    = ['OT', 'OTA'];

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
     *          attend_status, can_respond, response: { status, reason, help_reason } } }
     *   reason and help_reason are null when empty, never "".
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
                $this->copyToChat($reminder, AppointmentReminderChatService::CONFIRMED,
                    'Confirmed appointment on ' . $this->describeAppointment($reminder['appointment']) . '.');
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
     * The appointment stays as it is; staff follow up with the patient. Before a confirmation this
     * saves a Denied row with the reason; after one, the confirmed row keeps its status and the
     * reason goes in patient_apt_help_reason (replacing any earlier help request on that row).
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

            // Once confirmed, a help request must not turn the reply into a denial: it is kept on the
            // confirmed row in patient_apt_help_reason and patient_apt_msg_status stays Approved.
            $isConfirmed = $latest && (int) $latest->patient_apt_msg_status === self::STATUS_CONFIRMED;

            // A double tap or a page refresh must not stack identical rows for staff.
            $isRepeat = $isConfirmed
                ? (string) $latest->patient_apt_help_reason === $reason
                : $latest
                    && (int) $latest->patient_apt_msg_status === $helpStatus
                    && (string) $latest->patient_apt_msg_reason === $reason;

            if (!$isRepeat) {
                if ($isConfirmed) {
                    $latest->update(['patient_apt_help_reason' => $reason]);
                } else {
                    $this->saveResponse($reminder, $helpStatus, $reason);
                }

                $this->copyToChat($reminder, AppointmentReminderChatService::HELP_REQUESTED,
                    'Needs help attending appointment on ' . $this->describeAppointment($reminder['appointment']) . '. ' . $reason);
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
     * The first three open times later the same day, with the same provider and location. A time is
     * left out when the visit would run into lunch or another booking, and IC / OT / OTA visits are
     * only offered on the hour.
     *
     * Response:
     * - 200: { success: true, data: { attend_date, current_time, slots: [{ time: 'H:i', label: 'g:i A' }],
     *          message: string|null } }  message says why slots is empty
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
                    'slots'        => array_slice($slots, 0, self::MAX_SLOTS_SHOWN),
                    // Why there is nothing to pick, in the scheduling service's own words.
                    'message'      => empty($slots)
                        ? ($this->noSlotsReason ?: 'No other times are available that day.')
                        : null,
                ],
            ], 200);
        });
    }

    /**
     * POST /api/appointment-reminder/{token}/reschedule
     *
     * Moves the appointment to another open time on the same day. The appointment is modified
     * in place (same appointment id) and AdvancedMD is updated by the scheduling service.
     *
     * Request Payload:
     * - time (required, 'H:i' — must be one of the slots returned by time-slots)
     *
     * Response:
     * - 200: { success: true, message: string, data: { attend_date, time, end_time } }
     * - 422: { success: false, message: string }  includes an appointment that is already confirmed
     * - 4xx/5xx: { success: false, message: string }  message from the scheduling service
     */
    public function reschedule(Request $request, string $token): JsonResponse
    {
        return $this->handle($token, 'reschedule', function (array $reminder) use ($request) {
            if ($notScheduled = $this->rejectIfNotScheduled($reminder)) {
                return $notScheduled;
            }

            // A confirmed appointment (confirmed, or already rescheduled here) is not moved online
            // again; the reminder page hides "I need a different time" for it too.
            $latest = $this->latestResponse($reminder);

            if ($latest && (int) $latest->patient_apt_msg_status === self::STATUS_CONFIRMED) {
                return response()->json([
                    'success' => false,
                    'message' => 'This appointment is already confirmed. Please call us to change the time.',
                ], 422);
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

            // In-place modify: the SAME appointment row gets the new time (and AdvancedMD is updated),
            // so this link keeps pointing at the live appointment. The logged-in portal's reschedule
            // (PatientAppointmentController::appointmentReschedule) is a different call and is not
            // affected by this.
            $url = config('services.app_server.api_url')
                . '/modify-appointment-schedule/'
                . rawurlencode($this->patientName($reminder['patient_id'])) . '/' . $reminder['case_id'] . '/' . $appointment->id;

            // Keep the visit the same length. Without an end_time the scheduling service keeps the
            // OLD end time, which would send a wrong duration on to AdvancedMD.
            $endTime = !empty($appointment->end_time)
                ? Carbon::createFromFormat('H:i', $time)
                    ->addMinutes(Carbon::parse($appointment->time)->diffInMinutes(Carbon::parse($appointment->end_time)))
                    ->format('H:i')
                : null;

            // Only the time changes. provider_name and attend_type are read unconditionally by the
            // scheduling service, so they are always sent; 'S' keeps its slot and capacity checks on.
            $response = Http::acceptJson()->timeout(90)->post($url, array_filter([
                'attend_date'   => $appointment->attend_date,
                'time'          => $time,
                'end_time'      => $endTime,
                'department'    => $appointment->department,
                'service'       => $appointment->service,
                'provider_id'   => $appointment->provider_id,
                'attend_status' => 'S',
                'reason'        => 'Time changed by patient from appointment reminder link',
                // The scheduling service overwrites the appointment's notes with attend_notes, or
                // with `reason` when none is sent. Send the existing notes back so staff notes survive.
                'attend_notes'  => $appointment->attend_notes ?: null,
            ], fn ($value) => $value !== null) + [
                'attend_type'   => (string) $appointment->attend_type,
                'provider_name' => (string) $appointment->provider_name,
            ]);

            $data = $response->json() ?? [];

            // This service reports most refusals as HTTP 200 with status false and the reason in "error".
            if (!$response->successful() || ($data['status'] ?? null) !== 'success') {
                Log::channel('appointment')->warning('Appointment reminder reschedule rejected', [
                    'appt_id'   => $appointment->id,
                    'http_code' => $response->status(),
                    'response'  => $data,
                ]);

                $reason = collect([$data['message'] ?? null, $data['error'] ?? null])
                    ->first(fn ($value) => is_string($value) && $value !== '');

                // With no AdvancedMD appointment linked the service answers with a staff-facing
                // "Checked In ... provider changes" line, which means nothing to a patient.
                if (!$reason || str_contains($reason, 'does not allow provider changes')) {
                    $reason = 'This appointment cannot be changed online. Please call us to reschedule.';
                }

                return response()->json([
                    'success' => false,
                    'message' => $reason,
                ], $response->successful() ? 422 : $response->status());
            }

            // The patient picked this time themselves, so the appointment is recorded as confirmed
            // and is not chased for a reply again.
            $latest = $this->latestResponse($reminder);

            if (!$latest || (int) $latest->patient_apt_msg_status !== self::STATUS_CONFIRMED) {
                $this->saveResponse($reminder, self::STATUS_CONFIRMED, '');
            }

            $updated = AhcsAttendance::where('id', $appointment->id)->first(['attend_date', 'time', 'end_time']);

            $this->copyToChat($reminder, AppointmentReminderChatService::TIME_CHANGED,
                'Changed appointment time from ' . Carbon::parse($appointment->time)->format('g:i A')
                . ' to ' . Carbon::parse($updated->time)->format('g:i A')
                . '. Appointment was on ' . $this->describeAppointment($appointment) . '.');

            return response()->json([
                'success' => true,
                'message' => 'Appointment time updated successfully.',
                'data'    => [
                    'attend_date' => $updated->attend_date,
                    'time'        => $updated->time,
                    'end_time'    => $updated->end_time,
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
            'attend_date', 'time', 'end_time', 'attend_status', 'is_virtual', 'attend_notes',
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
                'status'      => $status,
                // null when there is no text (confirmed rows are saved with an empty reason).
                'reason'      => ($latest->patient_apt_msg_reason ?? '') !== '' ? $latest->patient_apt_msg_reason : null,
                // Help requested after the appointment was confirmed; status stays 'confirmed'.
                'help_reason' => ($latest->patient_apt_help_reason ?? '') !== '' ? $latest->patient_apt_help_reason : null,
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

    /**
     * Copy the reply into the support chat as a flagged, staff-only reminder thread.
     */
    private function copyToChat(array $reminder, string $kind, string $text): void
    {
        $name = $this->patientName($reminder['patient_id']);

        app(AppointmentReminderChatService::class)->record(
            $reminder['patient_id'],
            $reminder['case_id'],
            $reminder['appointment']->department,
            $kind,
            $text,
            $name !== 'Patient' ? $name : null
        );
    }

    private function describeAppointment($appointment): string
    {
        $when = Carbon::parse($appointment->attend_date . ' ' . $appointment->time);

        return $when->format('D, M j, Y') . ' at ' . $when->format('g:i A')
            . ($appointment->provider_name ? ' with ' . $appointment->provider_name : '')
            . ($appointment->department ? ' (' . $appointment->department . ')' : '');
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
                // Reminder flow only: no 24-hour notice, so a later time the same day can be
                // offered. The logged-in portal does not send this and keeps the 24-hour rule.
                'min_lead_hours' => 0,
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

        // A day with nothing bookable (same-day, holiday, provider leave...) still answers 200,
        // with status false and the reason in message.
        $this->noSlotsReason = $response->json('status') === false ? $response->json('message') : null;

        $currentTime = Carbon::parse($appointment->time)->format('H:i');
        $startTimes  = $response->json('start_times') ?? [];
        $slots       = [];

        // The service marks only lunch / booked START times as disabled, so a start just
        // before lunch is offered and then refused by modify-appointment-schedule. Drop any start
        // whose visit (same length as now) would run into a disabled (lunch / booked) time.
        $duration = !empty($appointment->end_time)
            ? Carbon::parse($appointment->time)->diffInMinutes(Carbon::parse($appointment->end_time))
            : 0;

        $blockedTimes = collect($startTimes)
            ->filter(fn ($slot) => !empty($slot['value']) && !empty($slot['disabled']))
            ->map(fn ($slot) => Carbon::parse($slot['value'])->format('H:i'))
            ->values();

        $onTheHourOnly = in_array(strtoupper((string) $appointment->attend_type), self::ON_THE_HOUR_VISIT_TYPES, true)
            || in_array(strtoupper((string) $appointment->service), self::ON_THE_HOUR_SERVICES, true);

        foreach ($startTimes as $slot) {
            if (empty($slot['value']) || !empty($slot['disabled'])) {
                continue;
            }

            $start = Carbon::parse($slot['value']);
            $value = $start->format('H:i');
            $end   = $start->copy()->addMinutes($duration)->format('H:i');

            if ($value <= $currentTime
                || ($onTheHourOnly && $start->minute !== 0)
                || $blockedTimes->contains(fn ($blocked) => $blocked > $value && $blocked < $end)) {
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
