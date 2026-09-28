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
        'case_id',
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
    /**
     * The dedup key for a direct conversation.
     *
     * WITHOUT a case this is the sorted identity pair, e.g. "3-9" — one thread per pair,
     * which is what a staff <-> staff thread wants and what every row created before
     * 2026-09-28 has.
     *
     * WITH a case it gains a "-c<id>" suffix, e.g. "3-9-c12345", giving a patient ONE
     * THREAD PER CASE in the same department. This needs no schema change beyond the
     * case_id column: `conversation_key` is already a unique string, and "3-9" and
     * "3-9-c12345" are simply two distinct values of it. The unique index does the
     * enforcing either way.
     *
     * The case is part of the KEY, so a thread's case can never be reassigned — pick a
     * different case and you get a different thread, which is the whole point.
     */
    public static function directKeyFor(int $chatUserIdA, int $chatUserIdB, ?int $caseId = null): string
    {
        $ids = [$chatUserIdA, $chatUserIdB];
        sort($ids);

        $key = implode('-', $ids);

        return $caseId === null ? $key : $key . '-c' . $caseId;
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
