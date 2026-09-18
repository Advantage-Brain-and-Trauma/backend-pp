<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatMessage extends Model
{
    protected $fillable = [
        'uuid',
        'conversation_id',
        'sender_chat_user_id',
        'sent_by_chat_user_id',
        'message',
        'message_type',
        'attachment',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChatMessage $message) {
            $message->uuid ??= (string) Str::uuid();
            $message->message_type ??= 'text';
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(ChatUser::class, 'sender_chat_user_id');
    }

    /**
     * The real staff member behind a department-queue reply.
     *
     * `sender` on such a message is the DEPARTMENT identity — that is what the patient sees
     * and what the broadcast payload carries. This is who actually typed it, kept for the
     * staff UI and the audit trail, and deliberately never exposed on a patient-facing
     * endpoint. Null on patient messages and on person-to-person conversations.
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(ChatUser::class, 'sent_by_chat_user_id');
    }
}
