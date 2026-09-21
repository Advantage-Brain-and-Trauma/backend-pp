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
            // Two different lists behind one endpoint. `staff` returns this user's own
            // person-to-person threads (no department, no lock); the default returns the
            // department queue.
            if ($request->input('scope') === 'staff') {
                return $this->staffConversations($request);
            }

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
            $staffId = (int) $this->staffIdentity($request)->id;

            return response()->json([
                'success' => true,
                'conversations' => $conversations
                    ->map(fn (Conversation $c) => $this->presentQueueConversation($c, $allowed, $unread, $latest, $staffId))
                    ->values(),
            ]);
        } catch (\Throwable $e) {
            return $this->failure('Chat staff conversation list error', $e, 'Unable to fetch conversations.');
        }
    }

    /**
     * The caller's own staff <-> staff threads. Membership is the only filter — these are
     * private two-person conversations and have nothing to do with the department queue.
     */
    private function staffConversations(Request $request): JsonResponse
    {
        $me = $this->staffIdentity($request);

        $conversations = Conversation::query()
            ->whereNull('department_chat_user_id')
            ->whereHas('participants', fn ($q) => $q->where('chat_user_id', $me->id))
            ->with(['participants.chatUser'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(min(max((int) $request->input('limit', 200), 1), 500))
            ->get();

        $ids = $conversations->pluck('id')->all();
        $unread = $this->staffUnreadCounts($ids, (int) $me->id);
        $latest = $this->latestMessages($ids);

        return response()->json([
            'success' => true,
            'conversations' => $conversations
                ->map(fn (Conversation $c) => $this->presentStaffConversation($c, $me, $unread, $latest))
                ->values(),
        ]);
    }

    /**
     * POST /api/chat/staff/conversations/start-staff
     *
     * Open (or return) the private thread between the caller and another staff member.
     * Deliberately separate from start(): that one builds a patient queue conversation and
     * every rule around it — department, case check, lock — is wrong here.
     */
    public function startStaff(Request $request): JsonResponse
    {
        $failed = $this->validateContext($request, [
            'peer_staff_external_id' => 'required|integer|min:1',
            'peer_staff_name' => 'nullable|string|max:255',
        ]);

        if ($failed) {
            return $failed;
        }

        try {
            $me = $this->staffIdentity($request);
            $peer = $this->departments->staffChatUser(
                (int) $request->input('peer_staff_external_id'),
                $request->input('peer_staff_name')
            );

            if ((int) $peer->id === (int) $me->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot start a conversation with yourself.',
                ], 422);
            }

            $conversation = $this->chatIdentityService->findOrCreateDirectConversation($me, $peer);

            // department_chat_user_id stays NULL — that is what marks this a staff thread and
            // keeps it out of every department queue.
            $conversation->load('participants.chatUser');

            return response()->json([
                'success' => true,
                'conversation' => $this->presentStaffConversation(
                    $conversation,
                    $me,
                    $this->staffUnreadCounts([$conversation->id], (int) $me->id),
                    $this->latestMessages([$conversation->id])
                ),
            ]);
        } catch (\Throwable $e) {
            return $this->failure('Chat staff peer start error', $e, 'Unable to start conversation.');
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
                    $this->latestMessages([$conversation->id]),
                    (int) $this->staffIdentity($request)->id
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

            $this->markRead($conversation, $this->staffIdentity($request));

            return response()->json([
                'success' => true,
                'messages' => $messages->through(
                    fn (ChatMessage $m) => $this->presentMessage($m, $conversation, (int) $this->staffIdentity($request)->id)
                ),
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
            'override' => 'nullable|boolean',
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

            $refusal = $this->claimForReply($request, $conversation, $staff);

            if ($refusal) {
                return $refusal;
            }

            // Patient thread: the DEPARTMENT is the sender, so the patient sees a team and not
            // a person, with the real author in sent_by. Staff thread: there is no department
            // (the column is null, and it is NOT NULL on chat_messages), so the staff member
            // sends as themselves and sent_by would be redundant.
            $message = $conversation->messages()->create([
                'sender_chat_user_id' => $conversation->isStaffConversation()
                    ? $staff->id
                    : $conversation->department_chat_user_id,
                'sent_by_chat_user_id' => $conversation->isStaffConversation() ? null : $staff->id,
                'message' => $request->input('message'),
                'message_type' => 'text',
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            // Replying is engaging with the thread, so it stops being unread for the sender.
            $this->markRead($conversation, $staff);

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
            'override' => 'nullable|boolean',
        ]);

        if ($failed) {
            return $failed;
        }

        try {
            $refusal = $this->refuseUnlessInQueue($request, $conversation);

            if ($refusal) {
                return $refusal;
            }

            // A staff <-> staff thread has no assignment concept. The UI hides the control,
            // but the endpoint refuses it too — otherwise a crafted call could stamp an owner
            // on a private two-person thread and make it look like queue traffic.
            if ($conversation->isStaffConversation()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff conversations are not assigned.',
                ], 422);
            }

            $assigneeId = $request->input('assign_to_staff_external_id');

            $assignee = $assigneeId !== null
                ? $this->departments->staffChatUser((int) $assigneeId, $request->input('assign_to_staff_name'))
                : null;

            // Manual assignment obeys the same lock as replying, or it would be a way around
            // it: claiming a live conversation someone else owns, or releasing theirs, would
            // hand the next reply to whoever did it. `override` is the supervisor path.
            $caller = $this->staffIdentity($request);

            if (!$conversation->isReplyableBy((int) $caller->id) && !$request->boolean('override')) {
                $conversation->loadMissing('assignee');

                return response()->json([
                    'success' => false,
                    'message' => 'This conversation is being handled by '
                        . ($conversation->assignee->name ?: 'another staff member') . '.',
                    'locked' => true,
                    'assigned_to' => $this->presentStaff($conversation->assignee),
                ], 409);
            }

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

            $this->markRead($conversation, $this->staffIdentity($request));

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
     * Take ownership of a conversation for this reply, or refuse because someone else holds it.
     *
     * THE RULE: a patient's message is offered to the whole department, but the FIRST staff
     * member to reply owns it and everyone else drops to read-only. Ownership lapses after
     * `chat.lock_minutes` of silence.
     *
     * THE CLAIM IS A SINGLE CONDITIONAL UPDATE, and that is the whole point. Reading
     * assigned_chat_user_id and then writing it would let two staff members who click Send at
     * the same moment both pass the check and both reply — which is exactly the behaviour this
     * is fixing. Here the database decides: whoever's UPDATE matches a row wins, the other
     * matches zero rows and is refused.
     *
     * `override` is a supervisor taking a stuck conversation from someone who has gone offline.
     * Medhiwa only sends it for staff holding the assign permission, and the portal trusts that
     * the same way it trusts the department list.
     */
    private function claimForReply(Request $request, Conversation $conversation, ChatUser $staff): ?JsonResponse
    {
        // Staff <-> staff is a two-person thread; there is no queue to arbitrate and both
        // participants must always be able to reply. Locking it would deadlock the pair.
        if ($conversation->isStaffConversation()) {
            return null;
        }

        $threshold = now()->subMinutes((int) config('chat.lock_minutes', 60));

        $claimed = Conversation::query()
            ->where('id', $conversation->id)
            ->where(function ($query) use ($threshold) {
                $query->whereNull('assigned_chat_user_id')
                    ->orWhereNull('last_message_at')
                    ->orWhere('last_message_at', '<=', $threshold);
            })
            ->update(['assigned_chat_user_id' => $staff->id]);

        if ($claimed > 0) {
            $conversation->refresh();

            return null;
        }

        // Zero rows matched: the conversation is live AND assigned. Fine if it is ours.
        $conversation->refresh();

        if ((int) $conversation->assigned_chat_user_id === (int) $staff->id) {
            return null;
        }

        if ($request->boolean('override')) {
            $conversation->update(['assigned_chat_user_id' => $staff->id]);

            Log::channel('chat')->info('Chat conversation taken over', [
                'conversation_uuid' => $conversation->uuid,
                'from_chat_user_id' => $conversation->getOriginal('assigned_chat_user_id'),
                'to_staff_external_id' => (int) $staff->external_id,
            ]);

            return null;
        }

        $conversation->loadMissing('assignee');

        return response()->json([
            'success' => false,
            'message' => 'This conversation is being handled by '
                . ($conversation->assignee->name ?: 'another staff member') . '.',
            'locked' => true,
            'assigned_to' => $this->presentStaff($conversation->assignee),
        ], 409);
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

        // Staff <-> staff thread: no department to check, so membership IS the authorisation.
        // Only the two participants may read or write, whatever departments they cover.
        if ($departmentId === null) {
            $caller = $this->staffIdentity($request);

            if (!$conversation->hasParticipant((int) $caller->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this conversation.',
                ], 403);
            }

            return null;
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

    /**
     * Marks a conversation read.
     *
     * Patient thread: read state lives on the DEPARTMENT's participant row, so it is shared —
     * once anyone opens it, it is read for the whole queue. Staff thread: read state is the
     * caller's own, as in any two-person chat.
     */
    private function markRead(Conversation $conversation, ?ChatUser $staff = null): void
    {
        $participantId = $conversation->isStaffConversation()
            ? ($staff?->id)
            : $conversation->department_chat_user_id;

        if ($participantId === null) {
            return;
        }

        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('chat_user_id', $participantId)
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
        array $latest,
        ?int $staffChatUserId = null
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
            // Derived, never stored: a conversation is "active" while a message has passed
            // within chat.lock_minutes. Its lock lapses with it.
            'is_active' => $conversation->isWithinLockWindow(),
            // Whether THIS caller may reply. The staff UI reads only this — it must never
            // re-derive the rule, or the two sides can disagree about who owns a thread.
            'can_reply' => $staffChatUserId === null
                || $conversation->isReplyableBy($staffChatUserId),
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

    /**
     * A staff <-> staff thread. No department, no patient, no assignment — `can_reply` is
     * always true for a participant, which is the whole difference from the patient queue.
     *
     * @param  array<int, int>  $unread
     * @param  array<int, ChatMessage>  $latest
     */
    private function presentStaffConversation(
        Conversation $conversation,
        ChatUser $me,
        array $unread,
        array $latest
    ): array {
        $peer = $conversation->participants
            ->first(fn (ConversationParticipant $p) => (int) $p->chat_user_id !== (int) $me->id);

        $last = $latest[$conversation->id] ?? null;

        return [
            'uuid' => $conversation->uuid,
            'is_staff_chat' => true,
            'department' => null,
            'patient' => null,
            'assigned_to' => null,
            'is_active' => $conversation->isWithinLockWindow(),
            'can_reply' => true,
            'peer' => $peer && $peer->chatUser ? [
                'uuid' => $peer->chatUser->uuid,
                'name' => $peer->chatUser->name,
                'staff_id' => (int) $peer->chatUser->external_id,
            ] : null,
            'unread_count' => (int) ($unread[$conversation->id] ?? 0),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'last_message' => $last ? [
                'preview' => mb_substr((string) $last->message, 0, 140),
                'from' => (int) $last->sender_chat_user_id === (int) $me->id ? 'me' : 'them',
                'created_at' => $last->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Unread counts for staff threads: messages someone ELSE sent since my own last_read_at.
     * Separate from unreadCounts(), which keys off the department participant and would return
     * nothing here.
     *
     * @param  int[]  $conversationIds
     * @return array<int, int>
     */
    private function staffUnreadCounts(array $conversationIds, int $staffChatUserId): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $rows = DB::table('chat_messages as m')
            ->join('conversation_participants as p', function ($join) use ($staffChatUserId) {
                $join->on('p.conversation_id', '=', 'm.conversation_id')
                    ->where('p.chat_user_id', '=', $staffChatUserId);
            })
            ->whereIn('m.conversation_id', $conversationIds)
            ->where('m.sender_chat_user_id', '!=', $staffChatUserId)
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

    private function presentMessage(ChatMessage $message, Conversation $conversation, ?int $meChatUserId = null): array
    {
        if ($conversation->isStaffConversation()) {
            // Two people, so "who sent it" is relative to the caller, not to a department.
            $from = (int) $message->sender_chat_user_id === (int) $meChatUserId ? 'me' : 'them';
        } else {
            $from = (int) $message->sender_chat_user_id === (int) $conversation->department_chat_user_id
                ? 'staff'
                : 'patient';
        }

        return [
            'id' => $message->uuid,
            'from' => $from,
            'sender_name' => $message->sender?->name,
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
