<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Services\ChatAttachmentService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message)
    {
        $this->message->loadMissing('sender');
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->message->conversation->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->uuid,
            'conversation_uuid' => $this->message->conversation->uuid,
            'sender_uuid' => $this->message->sender->uuid,
            'message' => $this->message->message,
            'message_type' => $this->message->message_type,
            'attachment' => $this->message->attachment,
            // Same resolved fields as the REST presenters. Without them a client listening on
            // the socket got only the storage path and could not build a working link.
            'attachment_url' => app(ChatAttachmentService::class)->url($this->message->attachment),
            'attachment_name' => $this->message->attachment
                ? app(ChatAttachmentService::class)->displayName($this->message->attachment)
                : null,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
