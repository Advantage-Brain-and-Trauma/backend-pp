<?php

namespace App\Services;

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
}
