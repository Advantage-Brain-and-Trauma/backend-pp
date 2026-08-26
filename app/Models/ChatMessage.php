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
}
