<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatUser;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Services\ChatDepartmentResolver;
use App\Services\ChatIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * STAFF side of the patient <-> staff queue chat, called server-to-server by Medhiwa.
 *
 * AUTHENTICATION AND TRUST
 * ------------------------
 * Every route here sits behind `chat.secret`: Medhiwa's server presents the shared secret and
 * names the staff member and the departments that staff member may see. Medhiwa is therefore
 * the authority on "who is this and what are they allowed to read" — it resolves the signed-in
 * user's permissions and locations before it calls. This portal enforces the rest: that the
 * conversation actually belongs to one of the departments the caller named. A staff browser
 * never reaches these routes and never holds the secret.
 *
 * WHY THESE ARE ALL POST
 * ----------------------
 * Each call carries its authorisation context (staff id + department list) in the body, so
 * even the read endpoints are POSTs. A GET with a body would be dropped by proxies and would
 * put department names in access logs.
 *
 * SENDER VS AUTHOR
 * ----------------
 * A staff reply is stored with sender_chat_user_id = the DEPARTMENT identity, so the patient
 * sees "Houston Care Team" and not a person, while sent_by_chat_user_id records the actual
 * staff member for the staff UI and the audit trail.
 *
 * READ STATE IS SHARED
 * --------------------
 * Unread is tracked on the department's conversation_participants row, so it is per QUEUE,
 * not per staff member: once one person opens a thread it is read for the whole department.
 * That is the intended queue semantic — a conversation someone has picked up should stop
 * shouting at everyone else.
 */
class ChatStaffController extends Controller
{
    public function __construct(
        private readonly ChatIdentityService $chatIdentityService,
        private readonly ChatDepartmentResolver $departments
    ) {
    }

    /**
     * POST /api/chat/staff/conversations
     *
     * The queue: every conversation belonging to the departments this staff member may see,
     * newest activity first.
     */
    public function conversations(Request $request): JsonResponse
    {
        $failed = $this->validateContext($request);

        if ($failed) {
            return $failed;
        }

        try {
            $allowed = $this->allowedDepartments($request);

            if ($allowed === []) {
                return response()->json([
                    'success' => true,
                    'conversations' => [],
                ]);
            }

            $limit = min(max((int) $request->input('limit', 200), 1), 500);

            $conversations = Conversation::query()
                ->whereIn('department_chat_user_id', array_keys($allowed))
                ->with(['participants.chatUser', 'assignee', 'department'])
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            // Resolved ONCE for the whole page, not per row — each is a single query.
            $ids = $conversations->pluck('id')->all();
            $unread = $this->unreadCounts($ids);
            $latest = $this->latestMessages($ids);

            return response()->json([
                'success' => true,
                'conversations' => $conversations
                    ->map(fn (Conversation $c) => $this->presentQueueConversation($c, $allowed, $unread, $latest))
                    ->values(),
            ]);
        } catch (\Throwable $e) {
            return $this->failure('Chat staff conversation list error', $e, 'Unable to fetch conversations.');
        }
    }

    /**
     * POST /api/chat/staff/conversations/start
     *
     * Staff-initiated conversation with a patient (decision R7). Returns the existing thread
     * when there already is one — a patient and a department always share exactly one.
     */
    public function start(Request $request): JsonResponse
    {
        $failed = $this->validateContext($request, [
            'patient_id' => 'required|integer|min:1',
            'department' => 'required|string|max:255',
            'patient_name' => 'nullable|string|max:255',
        ]);

        if ($failed) {
            return $failed;
        }

        try {
            $city = trim((string) $request->input('department'));

            // Checked by CITY, not by identity id, and BEFORE the identity is created: a
            // department nobody has written to yet has no chat_users row, so comparing
            // against the existing-identity list would refuse the very first conversation
            // in each department.
            if (!$this->callerMayUseCity($request, $city)) {
                return response()->json([
                    'success' => false,
                    'message' => 'That department is not available to this user.',
                ], 403);
            }

            $department = $this->departments->departmentChatUser($city);

            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'That department is not available to this user.',
                ], 403);
            }

            // Resolved after the identity exists, so the new department is included.
            $allowed = $this->allowedDepartments($request);

            $patientId = (int) $request->input('patient_id');

            // The same rule the patient side enforces: a conversation only exists where the
            // patient actually has a case. Without this, staff could open a thread in a
            // department that has no relationship to the patient.
            if (!$this->departments->patientMayUseDepartment([$patientId], $department)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This patient has no case in that department.',
                ], 422);
            }

            $patient = $this->departments->patientChatUser(
                $patientId,
                $request->input('patient_name')
            );

            $conversation = $this->chatIdentityService->findOrCreateDirectConversation($patient, $department);

            if ($conversation->department_chat_user_id === null) {
                $conversation->update(['department_chat_user_id' => $department->id]);
            }

            $conversation->load(['participants.chatUser', 'assignee', 'department']);

            return response()->json([
                'success' => true,
                'conversation' => $this->presentQueueConversation(
                    $conversation,
                    $allowed,
                    $this->unreadCounts([$conversation->id]),
                    $this->latestMessages([$conversation->id])
                ),
            ]);
        } catch (\Throwable $e) {
            return $this->failure('Chat staff conversation start error', $e, 'Unable to start conversation.');
        }
    }

    /**
     * POST /api/chat/staff/conversations/{conversation}/messages
     *
     * The transcript. Opening a thread marks it read for the whole queue.
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $failed = $this->validateContext($request);

        if ($failed) {
            return $failed;
        }

        try {
            $refusal = $this->refuseUnlessInQueue($request, $conversation);

            if ($refusal) {
                return $refusal;
            }

            $messages = $conversation->messages()
                ->with(['sender', 'sentBy'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->paginate(min(max((int) $request->input('per_page', 50), 1), 200));

            $this->markQueueRead($conversation);

            return response()->json([
                'success' => true,
                'messages' => $messages->through(fn (ChatMessage $m) => $this->presentMessage($m, $conversation)),
            ]);
        } catch (\Throwable $e) {
            return $this->failure('Chat staff message list error', $e, 'Unable to fetch messages.', $conversation);
        }
    }

    /**
     * POST /api/chat/staff/conversations/{conversation}/send
     *
     * Reply as the department, attributed to the staff member.
     */
    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $failed = $this->validateContext($request, [
            'message' => 'required|string|max:' . (int) config('chat.message_max_length', 5000),
        ]);

        if ($failed) {
            return $failed;
        }

        try {
            $refusal = $this->refuseUnlessInQueue($request, $conversation);

            if ($refusal) {
                return $refusal;
            }

            $staff = $this->staffIdentity($request);

            $message = $conversation->messages()->create([
                'sender_chat_user_id' => $conversation->department_chat_user_id,
                'sent_by_chat_user_id' => $staff->id,
                'message' => $request->input('message'),
                'message_type' => 'text',
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            // Replying is engaging with the thread, so it stops being unread for the queue.
            $this->markQueueRead($conversation);

            $message->load(['sender', 'sentBy', 'conversation']);

            // Best-effort real-time delivery on top of an already-persisted message: a Reverb
            // outage must not fail the send. Not ->toOthers(), which needs a socket id from a
            // browser — there is none on a server-to-server call.
            try {
                broadcast(new ChatMessageSent($message));
            } catch (\Throwable $e) {
                Log::channel('chat')->error('Chat staff broadcast error', [
                    'conversation_uuid' => $conversation->uuid,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message_data' => $this->presentMessage($message, $conversation),
            ]);
        } catch (\Throwable $e) {
            return $this->failure('Chat staff message send error', $e, 'Unable to send message.', $conversation);
        }
    }

    /**
     * POST /api/chat/staff/conversations/{conversation}/assign
     *
     * Claim a conversation, hand it to someone else, or release it (omit the assignee, or
     * send null). Advisory only — it does not stop anyone else in the queue replying.
     */
    public function assign(Request $request, Conversation $conversation): JsonResponse
    {
        $failed = $this->validateContext($request, [
            'assign_to_staff_external_id' => 'nullable|integer|min:1',
            'assign_to_staff_name' => 'nullable|string|max:255',
        ]);

        if ($failed) {
            return $failed;
        }

        try {
            $refusal = $this->refuseUnlessInQueue($request, $conversation);

            if ($refusal) {
                return $refusal;
            }

            $assigneeId = $request->input('assign_to_staff_external_id');

            $assignee = $assigneeId !== null
                ? $this->departments->staffChatUser((int) $assigneeId, $request->input('assign_to_staff_name'))
                : null;

            $conversation->update(['assigned_chat_user_id' => $assignee?->id]);
            $conversation->load('assignee');

            return response()->json([
                'success' => true,
                'assigned_to' => $this->presentStaff($conversation->assignee),
            ]);
        } catch (\Throwable $e) {
            return $this->failure('Chat staff assign error', $e, 'Unable to update assignment.', $conversation);
        }
    }

    /**
     * POST /api/chat/staff/conversations/{conversation}/read
     *
     * Mark the thread read for the queue without loading the transcript.
     */
    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $failed = $this->validateContext($request);

        if ($failed) {
            return $failed;
        }

        try {
            $refusal = $this->refuseUnlessInQueue($request, $conversation);

            if ($refusal) {
                return $refusal;
            }

            $this->markQueueRead($conversation);

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->failure('Chat staff mark-read error', $e, 'Unable to update the conversation.', $conversation);
        }
    }

    // -- authorisation ------------------------------------------------------

    /**
     * Validates the caller context every staff route carries, plus any route-specific rules.
     * Returns a 422 response when it fails, or null when it passes.
     */
    private function validateContext(Request $request, array $extra = []): ?JsonResponse
    {
        $validator = Validator::make($request->all(), array_merge([
            'staff_external_id' => 'required|integer|min:1',
            'staff_name' => 'nullable|string|max:255',
            'all_departments' => 'nullable|boolean',
            'departments' => 'required_without:all_departments|nullable|array',
            'departments.*' => 'string|max:255',
        ], $extra));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        return null;
    }

    /**
     * Department identities this call may touch, as [chat_user_id => display city].
     *
     * `all_departments` is how Medhiwa expresses a staff member who is not location-restricted
     * — either they hold the "all departments" permission, or they have no assigned locations,
     * which in Medhiwa means unrestricted rather than denied.
     *
     * @return array<int, string>
     */
    private function allowedDepartments(Request $request): array
    {
        if ($request->boolean('all_departments')) {
            return $this->departments->allDepartmentChatUserIds();
        }

        return $this->departments->departmentChatUserIds(
            array_map('strval', (array) $request->input('departments', []))
        );
    }

    /**
     * Whether the caller may act in a department, compared by CITY rather than by chat identity.
     *
     * Needed wherever the department identity may not exist yet — see start(). Uses the
     * resolver's canonical location id on both sides so spelling and casing differences
     * between what Medhiwa sends and what speciality_location stores do not matter.
     */
    private function callerMayUseCity(Request $request, string $city): bool
    {
        $locationId = $this->departments->canonicalLocationId($city);

        if ($locationId === null) {
            return false;
        }

        if ($request->boolean('all_departments')) {
            return true;
        }

        foreach ((array) $request->input('departments', []) as $allowedCity) {
            if ($this->departments->canonicalLocationId((string) $allowedCity) === $locationId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Refuses unless the conversation belongs to a department this caller may see.
     *
     * A conversation with no department (a person-to-person thread, or one created before the
     * queue model) is refused outright: it is not queue traffic and no staff member owns it.
     */
    private function refuseUnlessInQueue(Request $request, Conversation $conversation): ?JsonResponse
    {
        $departmentId = $conversation->department_chat_user_id;

        if ($departmentId === null) {
            return response()->json([
                'success' => false,
                'message' => 'This conversation is not part of a department queue.',
            ], 403);
        }

        if (!array_key_exists((int) $departmentId, $this->allowedDepartments($request))) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this conversation.',
            ], 403);
        }

        return null;
    }

    private function staffIdentity(Request $request): ChatUser
    {
        return $this->departments->staffChatUser(
            (int) $request->input('staff_external_id'),
            $request->input('staff_name')
        );
    }

    // -- read state ---------------------------------------------------------

    /** Marks the conversation read for the whole department queue. */
    private function markQueueRead(Conversation $conversation): void
    {
        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('chat_user_id', $conversation->department_chat_user_id)
            ->update(['last_read_at' => now()]);
    }

    /**
     * Unread counts for a set of conversations, in ONE query.
     *
     * Unread = a message NOT sent by the department (so: from the patient) that is newer than
     * the department participant's last_read_at.
     *
     * @param  int[]  $conversationIds
     * @return array<int, int>
     */
    private function unreadCounts(array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $rows = DB::table('chat_messages as m')
            ->join('conversations as c', 'c.id', '=', 'm.conversation_id')
            ->join('conversation_participants as p', function ($join) {
                $join->on('p.conversation_id', '=', 'c.id')
                    ->on('p.chat_user_id', '=', 'c.department_chat_user_id');
            })
            ->whereIn('m.conversation_id', $conversationIds)
            ->whereColumn('m.sender_chat_user_id', '!=', 'c.department_chat_user_id')
            ->where(function ($query) {
                $query->whereNull('p.last_read_at')
                    ->orWhereColumn('m.created_at', '>', 'p.last_read_at');
            })
            ->groupBy('m.conversation_id')
            ->selectRaw('m.conversation_id as conversation_id, COUNT(*) as unread')
            ->pluck('unread', 'conversation_id')
            ->all();

        return array_map('intval', $rows);
    }

    /**
     * The newest message of each conversation, for the list preview.
     *
     * @param  int[]  $conversationIds
     * @return array<int, ChatMessage>
     */
    private function latestMessages(array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $latestIds = ChatMessage::whereIn('conversation_id', $conversationIds)
            ->groupBy('conversation_id')
            ->selectRaw('MAX(id) as id')
            ->pluck('id')
            ->all();

        return ChatMessage::whereIn('id', $latestIds)
            ->get()
            ->keyBy('conversation_id')
            ->all();
    }

    // -- presentation -------------------------------------------------------

    /**
     * @param  array<int, string>  $allowed
     * @param  array<int, int>  $unread
     * @param  array<int, ChatMessage>  $latest
     */
    private function presentQueueConversation(
        Conversation $conversation,
        array $allowed,
        array $unread,
        array $latest
    ): array {
        $departmentId = (int) $conversation->department_chat_user_id;

        $patientParticipant = $conversation->participants
            ->first(fn (ConversationParticipant $p) => (int) $p->chat_user_id !== $departmentId);

        $last = $latest[$conversation->id] ?? null;

        return [
            'uuid' => $conversation->uuid,
            'department' => $allowed[$departmentId] ?? $this->departments->displayCity(
                (int) ($conversation->department?->external_id ?? 0)
            ),
            'patient' => $patientParticipant && $patientParticipant->chatUser ? [
                'uuid' => $patientParticipant->chatUser->uuid,
                'name' => $patientParticipant->chatUser->name,
                // The AHCS patient id, so Medhiwa can link the thread to Patient Details.
                'patient_id' => (int) $patientParticipant->chatUser->external_id,
            ] : null,
            'assigned_to' => $this->presentStaff($conversation->assignee),
            'unread_count' => (int) ($unread[$conversation->id] ?? 0),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'last_message' => $last ? [
                // A short preview only — the full body is fetched when a thread is opened.
                'preview' => mb_substr((string) $last->message, 0, 140),
                'from' => (int) $last->sender_chat_user_id === $departmentId ? 'staff' : 'patient',
                'created_at' => $last->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function presentMessage(ChatMessage $message, Conversation $conversation): array
    {
        $isStaff = (int) $message->sender_chat_user_id === (int) $conversation->department_chat_user_id;

        return [
            'id' => $message->uuid,
            'from' => $isStaff ? 'staff' : 'patient',
            'message' => $message->message,
            'message_type' => $message->message_type,
            'attachment' => $message->attachment,
            // Who actually typed a staff reply. Null on patient messages.
            'sent_by' => $this->presentStaff($message->sentBy),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function presentStaff(?ChatUser $staff): ?array
    {
        return $staff ? [
            'uuid' => $staff->uuid,
            'name' => $staff->name,
            // Medhiwa users.id, so the staff UI can match it to the signed-in user.
            'staff_id' => (int) $staff->external_id,
        ] : null;
    }

    /**
     * One failure path for every endpoint. Logs the conversation uuid and the exception only —
     * never a message body, a patient name or the request payload.
     */
    private function failure(string $logMessage, \Throwable $e, string $message, ?Conversation $conversation = null): JsonResponse
    {
        Log::channel('chat')->error($logMessage, [
            'conversation_uuid' => $conversation->uuid ?? null,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $message,
        ], 500);
    }
}
