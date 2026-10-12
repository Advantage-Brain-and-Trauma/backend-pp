<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Copies a patient's reply on the appointment reminder SMS link into the support chat, so
 * Medhiwa staff see it alongside their conversations.
 *
 * Each reply lands in a dedicated thread per patient + department + case, flagged with
 * Conversation::TYPE_APPOINTMENT_REMINDER, and each message carries the kind of reply in
 * message_type. Medhiwa lists these threads with scope "appointment_reminder" on
 * POST /api/chat/staff/conversations; they never appear in the normal queue.
 *
 * STAFF ONLY, by construction: the department is the thread's ONLY participant. The patient
 * is the message sender but not a participant, so nothing on the patient side — their
 * conversation list, the one-open-conversation rule, the channel authorisation — can see or
 * be affected by these threads.
 */
class AppointmentReminderChatService
{
    public const CONFIRMED = 'reminder_confirmed';
    public const HELP_REQUESTED = 'reminder_help_requested';
    public const TIME_CHANGED = 'reminder_time_changed';

    public const KINDS = [self::CONFIRMED, self::HELP_REQUESTED, self::TIME_CHANGED];

    public function __construct(private ChatDepartmentResolver $departments)
    {
    }

    /**
     * Never throws: the patient's reply is already saved, and a chat problem must not turn
     * it into an error on their screen.
     */
    public function record(
        int $patientId,
        int $caseId,
        ?string $department,
        string $kind,
        string $text,
        ?string $patientName = null
    ): void {
        try {
            $departmentUser = $department ? $this->departments->departmentChatUser($department) : null;

            if (!$departmentUser) {
                Log::channel('chat')->warning('Appointment reminder reply not copied to chat: department is not routable', [
                    'case_id' => $caseId,
                    'department' => $department,
                ]);

                return;
            }

            $patient = $this->departments->patientChatUser($patientId);

            // Only fill a missing name; the portal owns the name once the patient has signed in.
            if (!$patient->name && $patientName) {
                $patient->update(['name' => $patientName]);
            }

            $message = DB::transaction(function () use ($patient, $departmentUser, $caseId, $kind, $text) {
                $conversation = Conversation::firstOrCreate([
                    'conversation_key' => Conversation::reminderKeyFor($patient->id, $departmentUser->id, $caseId),
                ], [
                    'type' => Conversation::TYPE_APPOINTMENT_REMINDER,
                    'case_id' => $caseId,
                    'department_chat_user_id' => $departmentUser->id,
                ]);

                // The department's row holds the queue's shared read state.
                ConversationParticipant::firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'chat_user_id' => $departmentUser->id,
                ]);

                $message = $conversation->messages()->create([
                    'sender_chat_user_id' => $patient->id,
                    'message' => $text,
                    'message_type' => $kind,
                ]);

                $conversation->update(['last_message_at' => $message->created_at]);

                return $message;
            });

            $message->load('sender', 'conversation');

            try {
                broadcast(new ChatMessageSent($message));
            } catch (\Throwable $e) {
                Log::channel('chat')->error('Appointment reminder chat broadcast error', [
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('chat')->error('Appointment reminder reply not copied to chat', [
                'case_id' => $caseId,
                'kind' => $kind,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
