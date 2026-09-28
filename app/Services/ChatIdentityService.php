<?php

namespace App\Services;

use App\Exceptions\PatientHasOpenConversation;
use App\Models\ChatSession;
use App\Models\ChatUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatIdentityService
{
    /**
     * Resolve (or create) the chat_users row for an external identity
     * and issue a fresh, short-lived chat token for it.
     *
     * @return array{chat_token: string, chat_user: ChatUser}
     */
    public function issueFor(string $externalType, int $externalId, ?string $name = null): array
    {
        return DB::transaction(function () use ($externalType, $externalId, $name) {
            $chatUser = ChatUser::firstOrCreate(
                [
                    'external_type' => $externalType,
                    'external_id' => $externalId,
                ],
                [
                    'name' => $name,
                ]
            );

            if ($name && $chatUser->name !== $name) {
                $chatUser->update(['name' => $name]);
            }

            $token = Str::random(64);

            ChatSession::create([
                'token_hash' => hash('sha256', $token),
                'chat_user_id' => $chatUser->id,
                'expires_at' => now()->addMinutes((int) config('chat.session_minutes', 60)),
            ]);

            return [
                'chat_token' => $token,
                'chat_user' => $chatUser,
            ];
        });
    }

    /**
     * Find-or-create a direct conversation between two chat users.
     *
     * $caseId scopes the thread to ONE AHCS case: a patient with three cases in the same
     * department gets three threads, because the case is part of the conversation key.
     * Pass null for a conversation that has no case — a staff <-> staff thread — and the
     * behaviour is byte-for-byte what it was before cases existed.
     *
     * case_id is written only on CREATE, and needs no update path: the key contains the
     * case, so an existing row found by that key already carries the right one.
     */
    public function findOrCreateDirectConversation(ChatUser $a, ChatUser $b, ?int $caseId = null): \App\Models\Conversation
    {
        $key = \App\Models\Conversation::directKeyFor($a->id, $b->id, $caseId);

        return DB::transaction(function () use ($key, $a, $b, $caseId) {
            $conversation = \App\Models\Conversation::firstOrCreate([
                'conversation_key' => $key,
            ], [
                'type' => 'direct',
                'case_id' => $caseId,
            ]);

            foreach ([$a, $b] as $participant) {
                \App\Models\ConversationParticipant::firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'chat_user_id' => $participant->id,
                ]);
            }

            return $conversation;
        });
    }

    /**
     * Open — or resume — the patient's conversation about ONE case.
     *
     * A SEPARATE method from findOrCreateDirectConversation(), not another optional parameter,
     * because the two have different shapes. That one is a pure key lookup and stays that way
     * for staff <-> staff threads. This one is read-then-write and enforces a rule that spans
     * the patient's OTHER conversations, so folding it in would silently change the staff path.
     *
     * Two rules, both decided 2026-09-28:
     *
     *   1. ONE OPEN CONVERSATION PER PATIENT, globally — across every case and department. A
     *      patient with an open thread about case A must end it before opening one about case B.
     *   2. CLOSED IS FINAL. Re-opening a case starts the NEXT SESSION: a new row with its own
     *      key, never a resurrection of the closed one. Each episode stays a complete record.
     *
     * WHY THE ROW LOCK. Rule 1 is a check-then-insert, and MySQL has no partial unique index,
     * so "at most one row WHERE closed_at IS NULL" cannot be enforced by a constraint. Locking
     * the patient's own chat_users row serialises every open attempt FOR THAT PATIENT, which is
     * exactly the granularity the rule needs — two different patients never contend, and the
     * same patient cannot race themselves into two open threads. It also removes the session
     * race: the next session number is computed inside the same lock.
     *
     * @throws PatientHasOpenConversation when the patient already has a different one open.
     */
    public function openCaseConversation(
        ChatUser $patient,
        ChatUser $department,
        int $caseId
    ): \App\Models\Conversation {
        return DB::transaction(function () use ($patient, $department, $caseId) {
            // Serialises concurrent opens for THIS patient. Everything below runs inside it.
            ChatUser::whereKey($patient->id)->lockForUpdate()->first();

            $forThisCase = \App\Models\Conversation::query()
                ->where('case_id', $caseId)
                ->whereHas('participants', function ($query) use ($patient) {
                    $query->where('chat_user_id', $patient->id);
                });

            // Already open for this case: resume it. Checked FIRST, so that resuming your own
            // thread is never mistaken for starting a second one.
            $open = (clone $forThisCase)->whereNull('closed_at')->first();

            if ($open) {
                return $open;
            }

            // Any OTHER open thread blocks this one. Only reachable once the line above has
            // ruled out this case, so anything found here belongs to a different case.
            $blocking = \App\Models\Conversation::query()
                ->whereNull('closed_at')
                ->whereHas('participants', function ($query) use ($patient) {
                    $query->where('chat_user_id', $patient->id);
                })
                ->first();

            if ($blocking) {
                throw new PatientHasOpenConversation($blocking);
            }

            // Sessions count every thread this patient has had about this case, closed ones
            // included - that is what makes the number a lineage rather than a live count.
            $session = ((int) (clone $forThisCase)->max('session')) + 1;

            $conversation = \App\Models\Conversation::create([
                'conversation_key' => \App\Models\Conversation::directKeyFor(
                    $patient->id,
                    $department->id,
                    $caseId,
                    $session
                ),
                'type' => 'direct',
                'case_id' => $caseId,
                'session' => $session,
            ]);

            foreach ([$patient, $department] as $participant) {
                \App\Models\ConversationParticipant::firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'chat_user_id' => $participant->id,
                ]);
            }

            return $conversation;
        });
    }

    /**
     * End a conversation. Either side may do it, and it cannot be undone — writing about the
     * subject again opens the next session instead.
     *
     * The assignment is cleared at the same time: a closed thread is nobody's to answer, and
     * leaving a stale holder on it would make it look claimed in any view that lists closed
     * threads later.
     */
    public function closeConversation(\App\Models\Conversation $conversation, ChatUser $closedBy): \App\Models\Conversation
    {
        if ($conversation->isClosed()) {
            return $conversation;
        }

        $conversation->update([
            'closed_at' => now(),
            'closed_by_chat_user_id' => $closedBy->id,
            'assigned_chat_user_id' => null,
        ]);

        return $conversation;
    }
}
