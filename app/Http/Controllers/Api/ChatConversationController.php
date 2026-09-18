<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatUser;
use App\Models\Conversation;
use App\Services\ChatDepartmentResolver;
use App\Services\ChatIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * PATIENT-facing chat conversations (guard `chat`, i.e. a chat_token issued by
 * POST /api/chat/identify/patient).
 *
 * Staff do NOT come through here — they use /api/chat/staff/* with the shared secret, so
 * everything in this controller can assume the caller is a patient.
 */
class ChatConversationController extends Controller
{
    public function __construct(
        private readonly ChatIdentityService $chatIdentityService,
        private readonly ChatDepartmentResolver $departments
    ) {
    }

    /**
     * GET /api/chat/departments
     *
     * The departments this patient may start a conversation with — the distinct case
     * departments on their AHCS cases. The patient app uses this to offer a picker; sending
     * anything else to store() is refused.
     */
    public function departments(Request $request): JsonResponse
    {
        try {
            $chatUser = auth('chat')->user();

            $departments = collect($this->departments->departmentsForPatientIds($this->patientIdsFor($chatUser)))
                ->map(fn (string $city) => ['department' => $city])
                ->values();

            return response()->json([
                'success' => true,
                'departments' => $departments,
            ]);
        } catch (\Throwable $e) {
            Log::channel('chat')->error('Chat department list error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch departments.',
            ], 500);
        }
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
     * Starts (or returns the existing) conversation between this patient and one of THEIR
     * departments.
     *
     * RESTRICTED as of the queue-chat work. Previously this accepted any
     * peer_external_type + peer_external_id and created that chat identity if it did not
     * exist, so a patient could open a conversation with any id they cared to guess and no
     * check was made that they were allowed to talk to it. Now:
     *
     *   - the peer must be a DEPARTMENT (no person-to-person conversations from this
     *     endpoint), and
     *   - that department must be one the patient actually has a case in, and
     *   - the identity is resolved from a real speciality_location row rather than created
     *     from whatever the request asked for.
     *
     * Request shape — either:
     *     department: "Houston"                 (preferred; matches GET /chat/departments)
     * or the original pair, still accepted so an existing caller keeps working:
     *     peer_external_type: "department", peer_external_id: <canonical location id>
     *
     * PROXY ACCOUNTS: the chat identity carries the portal user's PRIMARY patient id only
     * (ChatAuthController::patient uses getPrimaryPatientId), so a proxy managing several
     * patients currently chats as the primary one. Per-patient proxy conversations are
     * decision R5 and are not built yet.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'department' => 'required_without:peer_external_id|nullable|string|max:255',
            'peer_external_type' => 'nullable|string|max:50',
            'peer_external_id' => 'required_without:department|nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $chatUser = auth('chat')->user();

            if ($chatUser->external_type !== (string) config('chat.types.patient', 'patient')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only a patient may start a conversation here.',
                ], 403);
            }

            $peer = $this->resolveRequestedDepartment($request);

            if (!$peer) {
                // Deliberately the same message whether the department does not exist or the
                // patient simply has no case there — a patient must not be able to probe
                // which departments the practice runs.
                return response()->json([
                    'success' => false,
                    'message' => 'That department is not available for this account.',
                ], 403);
            }

            if (!$this->departments->patientMayUseDepartment($this->patientIdsFor($chatUser), $peer)) {
                return response()->json([
                    'success' => false,
                    'message' => 'That department is not available for this account.',
                ], 403);
            }

            $conversation = $this->chatIdentityService->findOrCreateDirectConversation($chatUser, $peer);

            // Stamp the queue so the staff side can list a department's conversations without
            // walking participants. Set once, on the conversation's first resolution.
            if ($conversation->department_chat_user_id === null) {
                $conversation->update(['department_chat_user_id' => $peer->id]);
            }

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

    /**
     * The department identity the request is asking for, or null when it names none that
     * exists. Accepts the city name or the canonical location id.
     */
    private function resolveRequestedDepartment(Request $request): ?ChatUser
    {
        $city = trim((string) $request->input('department', ''));

        if ($city !== '') {
            return $this->departments->departmentChatUser($city);
        }

        $type = trim((string) $request->input('peer_external_type', ''));

        if ($type !== '' && $type !== (string) config('chat.types.department', 'department')) {
            return null;
        }

        $locationId = (int) $request->input('peer_external_id');
        $resolved = $locationId > 0 ? $this->departments->displayCity($locationId) : null;

        return $resolved !== null ? $this->departments->departmentChatUser($resolved) : null;
    }

    /**
     * The AHCS patient ids behind a patient chat identity. One id today — see the proxy note
     * on store().
     *
     * @return int[]
     */
    private function patientIdsFor(ChatUser $chatUser): array
    {
        if ($chatUser->external_type !== (string) config('chat.types.patient', 'patient')) {
            return [];
        }

        return [(int) $chatUser->external_id];
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
