<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PatientHasOpenConversation;
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
     * GET /api/chat/cases
     *
     * The cases this patient may open a thread about — the picker behind POST /conversations.
     *
     * Unlike GET /chat/departments, which collapses several cases in one city into a single
     * row, this returns ONE ENTRY PER CASE, because since 2026-09-28 a thread is scoped to a
     * case rather than to a department.
     *
     * Each entry carries the department (derived, so the client never has to choose one) and
     * the date of injury, which is the only field a patient would recognise when telling two
     * cases in the same city apart — ahcs_cases has no title or reference column.
     */
    public function cases(Request $request): JsonResponse
    {
        try {
            $chatUser = auth('chat')->user();

            return response()->json([
                'success' => true,
                'cases' => $this->departments->casesForPatientIds($this->patientIdsFor($chatUser)),
            ]);
        } catch (\Throwable $e) {
            Log::channel('chat')->error('Chat case list error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch cases.',
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
     * Request shape (changed 2026-09-28 — a thread is now scoped to a CASE):
     *     case_id: 12345                        (required; from GET /chat/cases)
     *     department: "Houston"                 (optional, cross-checked against the case)
     *
     * ONE THREAD PER CASE. A patient with three cases in Houston now has three threads,
     * not one. The department is DERIVED from the case and never taken from the request,
     * since a case belongs to exactly one department.
     *
     * The old peer_external_type + peer_external_id pair is GONE: it can name a department
     * but not a case, so it can no longer identify a thread. Nothing consumed it — there is
     * no patient app yet.
     *
     * PROXY ACCOUNTS: the chat identity carries the portal user's PRIMARY patient id only
     * (ChatAuthController::patient uses getPrimaryPatientId), so a proxy managing several
     * patients currently chats as the primary one. Per-patient proxy conversations are
     * decision R5 and are not built yet.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'case_id' => 'required|integer|min:1',
            'department' => 'nullable|string|max:255',
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

            /*
             * The CASE decides everything: whether this patient may write at all, and which
             * department the thread belongs to. One query answers both, and the department
             * is never read from the request.
             *
             * The refusal below is deliberately the same message whether the case does not
             * exist, belongs to another patient, or sits in a city with no location row —
             * a patient must not be able to probe which cases or departments exist.
             */
            $case = $this->departments->caseForPatientIds(
                $this->patientIdsFor($chatUser),
                (int) $request->input('case_id')
            );

            if (!$case) {
                return response()->json([
                    'success' => false,
                    'message' => 'That department is not available for this account.',
                ], 403);
            }

            // `department` is optional and CROSS-CHECKED, never trusted: a mismatch means the
            // client thinks it is opening a different case than the one it named, and opening
            // the wrong thread silently is worse than refusing.
            $requestedCity = trim((string) $request->input('department', ''));

            if ($requestedCity !== '') {
                $requestedLocationId = $this->departments->canonicalLocationId($requestedCity);
                $caseLocationId = $this->departments->canonicalLocationId($case['department']);

                if ($requestedLocationId === null || $requestedLocationId !== $caseLocationId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'That department is not available for this account.',
                    ], 403);
                }
            }

            $peer = $this->departments->departmentChatUser($case['department']);

            if (!$peer) {
                return response()->json([
                    'success' => false,
                    'message' => 'That department is not available for this account.',
                ], 403);
            }

            /*
             * ONE OPEN CONVERSATION PER PATIENT, globally. Resuming their own open thread for
             * this case is fine; anything else is refused with 409 and the uuid of the thread
             * in the way, so the app can offer to end it rather than leaving the patient to
             * hunt for it.
             */
            try {
                $conversation = $this->chatIdentityService->openCaseConversation(
                    $chatUser,
                    $peer,
                    $case['case_id']
                );
            } catch (PatientHasOpenConversation $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an open conversation. End it before starting another.',
                    'open_conversation' => [
                        'uuid' => $e->openConversation->uuid,
                        'case_id' => $e->openConversation->case_id !== null
                            ? (int) $e->openConversation->case_id
                            : null,
                    ],
                ], 409);
            }

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
     * POST /api/chat/conversations/{conversation}/close
     *
     * The patient ends their conversation. Final: neither side may write to it afterwards, and
     * asking about the same case again opens the NEXT SESSION rather than reviving this one.
     *
     * Idempotent, so a double tap on a slow connection is not an error.
     */
    public function close(Request $request, Conversation $conversation): JsonResponse
    {
        try {
            $chatUser = auth('chat')->user();

            if (!$conversation->hasParticipant($chatUser->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a participant in this conversation.',
                ], 403);
            }

            $this->chatIdentityService->closeConversation($conversation, $chatUser);

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::channel('chat')->error('Chat conversation close error', [
                'conversation_uuid' => $conversation->uuid ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to end this conversation.',
            ], 500);
        }
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
            // WHICH CASE this thread is about. Without it the patient app cannot tell two
            // threads in the same department apart — both render as "<City> Care Team" and
            // the whole point of per-case threads is invisible. Null on rows created before
            // threads were case-scoped.
            'case_id' => $conversation->case_id !== null ? (int) $conversation->case_id : null,
            // Which attempt at this case this is. 1 for every thread that has never been
            // ended; 2+ after a previous one was closed.
            'session' => (int) ($conversation->session ?? 1),
            // Non-null means ENDED: readable, never writable again.
            'closed_at' => $conversation->closed_at?->toIso8601String(),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'peer' => $peerParticipant ? [
                'uuid' => $peerParticipant->chatUser->uuid,
                'external_type' => $peerParticipant->chatUser->external_type,
                'name' => $peerParticipant->chatUser->name,
            ] : null,
        ];
    }
}
