<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatUser;
use App\Models\Conversation;
use App\Services\ChatIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatConversationController extends Controller
{
    public function __construct(private readonly ChatIdentityService $chatIdentityService)
    {
    }

    /**
     * GET /api/chat/conversations
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $chatUser = auth('chat')->user();

            $conversations = Conversation::whereHas('participants', function ($query) use ($chatUser) {
                $query->where('chat_user_id', $chatUser->id);
            })
                ->with(['participants.chatUser'])
                ->orderByDesc('last_message_at')
                ->get()
                ->map(fn (Conversation $conversation) => $this->presentConversation($conversation, $chatUser));

            return response()->json([
                'success' => true,
                'conversations' => $conversations,
            ]);
        } catch (\Throwable $e) {
            Log::channel('chat')->error('Chat conversation list error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch conversations.',
            ], 500);
        }
    }

    /**
     * POST /api/chat/conversations
     *
     * Starts (or returns the existing) direct conversation with a peer.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'peer_external_type' => 'required|string|max:50',
            'peer_external_id' => 'required|integer|min:1',
            'peer_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $chatUser = auth('chat')->user();

            $peer = ChatUser::firstOrCreate(
                [
                    'external_type' => $request->string('peer_external_type')->toString(),
                    'external_id' => (int) $request->input('peer_external_id'),
                ],
                [
                    'name' => $request->input('peer_name'),
                ]
            );

            if ($peer->id === $chatUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot start a conversation with yourself.',
                ], 422);
            }

            $conversation = $this->chatIdentityService->findOrCreateDirectConversation($chatUser, $peer);
            $conversation->load('participants.chatUser');

            return response()->json([
                'success' => true,
                'conversation' => $this->presentConversation($conversation, $chatUser),
            ]);
        } catch (\Throwable $e) {
            Log::channel('chat')->error('Chat conversation create error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to start conversation.',
            ], 500);
        }
    }

    private function presentConversation(Conversation $conversation, ChatUser $chatUser): array
    {
        $peerParticipant = $conversation->participants
            ->first(fn ($participant) => $participant->chat_user_id !== $chatUser->id);

        return [
            'uuid' => $conversation->uuid,
            'type' => $conversation->type,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'peer' => $peerParticipant ? [
                'uuid' => $peerParticipant->chatUser->uuid,
                'external_type' => $peerParticipant->chatUser->external_type,
                'name' => $peerParticipant->chatUser->name,
            ] : null,
        ];
    }
}
