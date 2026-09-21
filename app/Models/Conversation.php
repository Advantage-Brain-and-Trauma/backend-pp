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

    /**
     * A staff <-> staff conversation: an ordinary direct thread between two `staff` identities,
     * with NO department. Everything department-shaped — queue listing, the first-responder
     * lock, read state shared across a department — applies only to patient conversations and
     * must be skipped for these. Both participants can always reply.
     */
    public function isStaffConversation(): bool
    {
        return $this->department_chat_user_id === null;
    }

    /**
     * Whether this conversation is still "live" — i.e. a message has passed within the lock
     * window. DERIVED from last_message_at, never stored, so it lapses on its own with no
     * scheduled job and no status column to drift.
     */
    public function isWithinLockWindow(): bool
    {
        if ($this->last_message_at === null) {
            return false;
        }

        return $this->last_message_at->gt(
            now()->subMinutes((int) config('chat.lock_minutes', 60))
        );
    }

    /**
     * Whether a given staff chat identity may REPLY right now.
     *
     * Unassigned or lapsed => anyone in the department may take it. Otherwise only the holder.
     * Read access is unaffected: other staff keep seeing the thread, read-only.
     */
    public function isReplyableBy(int $staffChatUserId): bool
    {
        if ($this->assigned_chat_user_id === null || !$this->isWithinLockWindow()) {
            return true;
        }

        return (int) $this->assigned_chat_user_id === $staffChatUserId;
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
