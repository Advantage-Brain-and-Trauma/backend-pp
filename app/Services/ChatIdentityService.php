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
     */
    public function findOrCreateDirectConversation(ChatUser $a, ChatUser $b): \App\Models\Conversation
    {
        $key = \App\Models\Conversation::directKeyFor($a->id, $b->id);

        return DB::transaction(function () use ($key, $a, $b) {
            $conversation = \App\Models\Conversation::firstOrCreate([
                'conversation_key' => $key,
            ], [
                'type' => 'direct',
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
