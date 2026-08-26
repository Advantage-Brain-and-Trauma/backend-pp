<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A chat-only identity, decoupled from the source system's own
 * auth (Patient Portal JWT, doctor session/token, ...). Every
 * participant is resolved to exactly one row here, keyed by
 * [external_type, external_id], and is addressed everywhere else
 * in the chat system only by its uuid.
 */
class ChatUser extends Model implements Authenticatable
{
    protected $fillable = [
        'uuid',
        'external_type',
        'external_id',
        'name',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChatUser $chatUser) {
            $chatUser->uuid ??= (string) Str::uuid();
            $chatUser->status ??= 'active';
        });
    }

    public function conversationParticipants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_chat_user_id');
    }

    // -- Illuminate\Contracts\Auth\Authenticatable ---------------------

    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName();
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): ?string
    {
        return null;
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
