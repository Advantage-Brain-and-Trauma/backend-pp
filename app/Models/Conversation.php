<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    protected $fillable = [
        'uuid',
        'conversation_key',
        'type',
        'assigned_chat_user_id',
        'department_chat_user_id',
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
     * The department queue this conversation belongs to, when the patient's peer is a
     * department identity rather than a person. Nullable: a plain person-to-person
     * conversation has none.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(ChatUser::class, 'department_chat_user_id');
    }

    /**
     * The staff member who has claimed this conversation, if any. A claim is advisory —
     * it does not stop anyone else in the queue replying (decision R3).
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(ChatUser::class, 'assigned_chat_user_id');
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
