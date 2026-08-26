<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatMessageController extends Controller
{
    /**
     * GET /api/chat/conversations/{conversation}/messages
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        try {
            $chatUser = auth('chat')->user();

            if (!$conversation->hasParticipant($chatUser->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a participant in this conversation.',
                ], 403);
            }

            $messages = $conversation->messages()
                ->with('sender')
                ->orderBy('created_at')
                ->paginate((int) $request->query('per_page', 50));

            $conversation->participants()
                ->where('chat_user_id', $chatUser->id)
                ->update(['last_read_at' => now()]);

            return response()->json([
                'success' => true,
                'messages' => $messages->through(fn (ChatMessage $message) => $this->presentMessage($message)),
            ]);
        } catch (\Throwable $e) {
            Log::channel('chat')->error('Chat message list error', [
                'conversation_uuid' => $conversation->uuid ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch messages.',
            ], 500);
        }
    }

    /**
     * POST /api/chat/conversations/{conversation}/messages
     */
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required_without:attachment|nullable|string',
            'message_type' => 'nullable|string|in:text,image,file',
            'attachment' => 'nullable|string|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $chatUser = auth('chat')->user();

            if (!$conversation->hasParticipant($chatUser->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a participant in this conversation.',
                ], 403);
            }

            $message = $conversation->messages()->create([
                'sender_chat_user_id' => $chatUser->id,
                'message' => $request->input('message'),
                'message_type' => $request->input('message_type', 'text'),
                'attachment' => $request->input('attachment'),
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            $message->load('sender', 'conversation');

            // Broadcasting is best-effort real-time delivery on top of an
            // already-persisted message — a Reverb outage must not fail
            // the send itself.
            try {
                broadcast(new ChatMessageSent($message))->toOthers();
            } catch (\Throwable $e) {
                Log::channel('chat')->error('Chat message broadcast error', [
                    'conversation_uuid' => $conversation->uuid,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message_data' => $this->presentMessage($message),
            ]);
        } catch (\Throwable $e) {
            Log::channel('chat')->error('Chat message send error', [
                'conversation_uuid' => $conversation->uuid ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to send message.',
            ], 500);
        }
    }

    private function presentMessage(ChatMessage $message): array
    {
        return [
            'id' => $message->uuid,
            'sender' => [
                'uuid' => $message->sender->uuid,
                'external_type' => $message->sender->external_type,
                'name' => $message->sender->name,
            ],
            'message' => $message->message,
            'message_type' => $message->message_type,
            'attachment' => $message->attachment,
            'read_at' => $message->read_at?->toIso8601String(),
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
