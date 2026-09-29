<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Services\ChatAttachmentService;
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
     * POST /api/chat/attachments
     *
     * Upload a file, get back the reference to put in `attachment` when sending. Deliberately
     * SEPARATE from sending: a 100 MB upload that fails should not also lose the message, and a
     * patient who picks the wrong file finds out before they have written anything.
     *
     * `mimes` checks the type guessed from the CONTENTS, not the client's filename, so a
     * renamed executable does not get through. The size ceiling here is only one of three -
     * nginx's client_max_body_size and PHP's upload_max_filesize / post_max_size cap it again,
     * and a request over THEIR limit never reaches this method: nginx answers 413 and PHP
     * discards the body, which surfaces as an empty request rather than a validation error.
     *
     * PHI: never log the filename or the contents.
     */
    public function storeAttachment(Request $request, ChatAttachmentService $attachments): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:' . implode(',', ChatAttachmentService::ALLOWED_EXTENSIONS)
                . '|max:' . (int) config('chat.attachments.max_kb', 102400),
        ], [
            'file.mimes' => ChatAttachmentService::TYPE_MESSAGE,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            return response()->json([
                'success' => true,
                'attachment' => $attachments->store($request->file('file')),
            ]);
        } catch (\Throwable $e) {
            Log::channel('chat')->error('Chat attachment upload error', [
                // Deliberately no filename here - it is patient data.
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to upload that file.',
            ], 500);
        }
    }

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
        // max on `message` added with the queue-chat work: there was previously no length
        // limit at all, so a single request could store an unbounded TEXT body and broadcast
        // it to every subscriber on the channel.
        $validator = Validator::make($request->all(), [
            'message' => 'required_without:attachment|nullable|string|max:' . (int) config('chat.message_max_length', 5000),
            'message_type' => 'nullable|string|in:text,image,file',
            'attachment' => 'nullable|string|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Only a file our own upload endpoint stored - never a URL or an arbitrary path. Before
        // this a patient could send any link and it rendered on the staff screen as an attachment.
        if ($request->filled('attachment')
            && !app(ChatAttachmentService::class)->isStoredReference((string) $request->input('attachment'))) {
            return response()->json([
                'success' => false,
                'message' => 'That attachment could not be found. Please attach the file again.',
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

            /*
             * Closed is FINAL (decision 2026-09-28). Not the same thing as the INACTIVE state
             * handled just below: inactive means the lock lapsed and the department competes
             * again, closed means the episode is over and the next message belongs to a new
             * session. 409 rather than 403 - nothing is wrong with the caller's rights.
             */
            if ($conversation->isClosed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This conversation has ended. Start a new one to continue.',
                    'closed' => true,
                ], 409);
            }

            // A patient writing into a conversation that has gone INACTIVE reopens it for the
            // whole department: the previous owner's lock is released here, so the next staff
            // member to reply takes it. Evaluated BEFORE last_message_at is touched — updating
            // that first would make a long-dormant conversation look live again and leave the
            // old assignment in place, locking out everyone but a staff member who may well
            // have finished with it hours ago.
            if ($conversation->assigned_chat_user_id !== null && !$conversation->isWithinLockWindow()) {
                $conversation->update(['assigned_chat_user_id' => null]);
            }

            $message = $conversation->messages()->create([
                'sender_chat_user_id' => $chatUser->id,
                'message' => $request->input('message'),
                // A file with the default type would be stored as 'text'; the client's own
                // image/file choice is kept when it made one.
                'message_type' => $request->filled('attachment')
                    ? (in_array($request->input('message_type'), ['image', 'file'], true) ? $request->input('message_type') : 'file')
                    : 'text',
                'attachment' => $request->filled('attachment') ? $request->input('attachment') : null,
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
            // Resolved for the client so neither app has to know where files live.
            'attachment_url' => app(ChatAttachmentService::class)->url($message->attachment),
            'attachment_name' => $message->attachment
                ? app(ChatAttachmentService::class)->displayName($message->attachment)
                : null,
            'read_at' => $message->read_at?->toIso8601String(),
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
