<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatUser;
use App\Services\ChatIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatAuthController extends Controller
{
    public function __construct(private readonly ChatIdentityService $chatIdentityService)
    {
    }

    /**
     * POST /api/chat/identify/patient
     *
     * Issues a chat identity/token for the currently JWT-authenticated
     * Patient Portal user.
     */
    public function patient(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $patientId = $user?->getPrimaryPatientId();

            if (!$patientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No patient is linked to this account.',
                ], 422);
            }

            $result = $this->chatIdentityService->issueFor(
                'patient',
                $patientId,
                $user->name
            );

            return $this->identityResponse($result);
        } catch (\Throwable $e) {
            Log::channel('chat')->error('Chat patient identify error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to issue chat identity.',
            ], 500);
        }
    }

    /**
     * POST /api/chat/identify/external
     *
     * Server-to-server identify for non-patient systems (doctor / Medhiwa-23),
     * authenticated with the X-CHAT-SECRET shared secret instead of a user
     * session — the caller's own backend vouches for the external id.
     */
    public function external(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'external_type' => 'required|string|max:50',
            'external_id' => 'required|integer|min:1',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $result = $this->chatIdentityService->issueFor(
                $request->string('external_type')->toString(),
                (int) $request->input('external_id'),
                $request->input('name')
            );

            return $this->identityResponse($result);
        } catch (\Throwable $e) {
            Log::channel('chat')->error('Chat external identify error', [
                'external_type' => $request->input('external_type'),
                'external_id' => $request->input('external_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to issue chat identity.',
            ], 500);
        }
    }

    /**
     * @param array{chat_token: string, chat_user: ChatUser} $result
     */
    private function identityResponse(array $result): JsonResponse
    {
        return response()->json([
            'success' => true,
            'chat_token' => $result['chat_token'],
            'chat_user' => [
                'uuid' => $result['chat_user']->uuid,
                'external_type' => $result['chat_user']->external_type,
                'name' => $result['chat_user']->name,
            ],
        ]);
    }
}
