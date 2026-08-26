<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    protected $fillable = [
        'uuid',
        'conversation_key',
        'type',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation) {
            $conversation->uuid ??= (string) Str::uuid();
            $conversation->type ??= 'direct';
        });
    }

    public function participants(): HasMany
    {
        return $this->hasMany(
            ConversationParticipant::class
        );
    }

    public function messages(): HasMany
    {
        return $this->hasMany(
            ChatMessage::class
        );
    }

    /**
     * Deterministic dedup key for a direct conversation between two
     * chat users, independent of argument order.
     */
    public static function directKeyFor(int $chatUserIdA, int $chatUserIdB): string
    {
        $ids = [$chatUserIdA, $chatUserIdB];
        sort($ids);

        return implode('-', $ids);
    }

    public function hasParticipant(int $chatUserId): bool
    {
        return $this->participants()
            ->where('chat_user_id', $chatUserId)
            ->exists();
    }

    /**
     * Resolve conversations by uuid in route model binding, never
     * by the internal numeric id.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
