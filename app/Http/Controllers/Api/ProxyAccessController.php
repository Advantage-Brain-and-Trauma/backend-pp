<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ProxyInvitationMail;
use App\Models\ProxyAccess;
use App\Models\ProxyAccessHistory;
use App\Models\User;
use App\Models\AhcsCase;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Token;

class ProxyAccessController extends Controller
{
    // -------------------------------------------------------------------------
    // Patient: Invite a proxy
    // POST /api/proxy/invite
    // -------------------------------------------------------------------------
    public function invite(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email'        => 'required|email',
                'relationship' => 'required|string|max:100',
                'access_level' => 'required|in:full,limited,read_only',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()], 422);
            }

            $patient = auth()->user();

            // Block inviting yourself
            if (strtolower($patient->email) === strtolower($request->email)) {
                return response()->json(['success' => false, 'message' => 'You cannot invite yourself as a proxy.'], 422);
            }

            // Check for existing active or pending access
            $existing = ProxyAccess::where('patient_user_id', $patient->id)
                ->where('proxy_email', strtolower($request->email))
                ->whereIn('status', ['pending', 'active'])
                ->first();

            if ($existing) {
                $msg = $existing->status === 'active'
                    ? 'This person already has active proxy access.'
                    : 'An invitation is already pending for this email.';
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            $token = Str::random(64);

            $proxy = ProxyAccess::create([
                'patient_user_id'  => $patient->id,
                'proxy_user_id'    => null,
                'proxy_email'      => strtolower($request->email),
                'relationship'     => $request->relationship,
                'access_level'     => $request->access_level,
                'status'           => 'pending',
                'invitation_token' => $token,
                'token_expires_at' => now()->addHours(48),
                'invited_at'       => now(),
            ]);

            $acceptUrl   = config('app.frontend_url') . '/proxy/accept/' . $token;
            $patientName = $patient->name ?? ($patient->email);

            Mail::to($request->email)->send(new ProxyInvitationMail($proxy, $patientName, $acceptUrl));

            Log::channel('auth')->info('Proxy invitation sent', [
                'patient_user_id' => $patient->id,
                'proxy_email'     => $request->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Invitation sent to {$request->email}.",
            ]);
        } catch (\Throwable $e) {
            Log::error('ProxyAccessController@invite failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Something went wrong.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Patient: List all proxies
    // GET /api/proxy/list
    // -------------------------------------------------------------------------
    public function list(Request $request): JsonResponse
    {
        try {
            $patient = auth()->user();

            $proxies = ProxyAccess::where('patient_user_id', $patient->id)
                ->with(['history' => fn($q) => $q->latest('accessed_at')->limit(1)])
                ->orderByDesc('created_at')
                ->get()
                ->map(function (ProxyAccess $p) {
                    return [
                        'id'            => $p->id,
                        'proxy_email'   => $p->proxy_email,
                        'relationship'  => $p->relationship,
                        'access_level'  => $p->access_level,
                        'status'        => $p->status,
                        'invited_at'    => $p->invited_at?->toIso8601String(),
                        'accepted_at'   => $p->accepted_at?->toIso8601String(),
                        'revoked_at'    => $p->revoked_at?->toIso8601String(),
                        'last_activity' => $p->history->first()?->accessed_at?->toIso8601String(),
                    ];
                });

            return response()->json(['success' => true, 'proxies' => $proxies]);
        } catch (\Throwable $e) {
            Log::error('ProxyAccessController@list failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Something went wrong.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Patient: Revoke proxy access
    // DELETE /api/proxy/{id}/revoke
    // -------------------------------------------------------------------------
    public function revoke(Request $request, int $id): JsonResponse
    {
        try {
            $patient = auth()->user();

            $proxy = ProxyAccess::where('id', $id)
                ->where('patient_user_id', $patient->id)
                ->first();

            if (!$proxy) {
                return response()->json(['success' => false, 'message' => 'Proxy access record not found.'], 404);
            }

            if ($proxy->status === 'revoked') {
                return response()->json(['success' => false, 'message' => 'Access is already revoked.'], 422);
            }

            $proxy->update([
                'status'     => 'revoked',
                'revoked_at' => now(),
            ]);

            Log::channel('auth')->info('Proxy access revoked', [
                'patient_user_id' => $patient->id,
                'proxy_access_id' => $proxy->id,
                'proxy_email'     => $proxy->proxy_email,
            ]);

            return response()->json(['success' => true, 'message' => 'Proxy access has been revoked.']);
        } catch (\Throwable $e) {
            Log::error('ProxyAccessController@revoke failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Something went wrong.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Patient: View proxy access history
    // GET /api/proxy/{id}/history
    // -------------------------------------------------------------------------
    public function history(Request $request, int $id): JsonResponse
    {
        try {
            $patient = auth()->user();

            $proxy = ProxyAccess::where('id', $id)
                ->where('patient_user_id', $patient->id)
                ->first();

            if (!$proxy) {
                return response()->json(['success' => false, 'message' => 'Proxy access record not found.'], 404);
            }

            $history = ProxyAccessHistory::where('proxy_access_id', $proxy->id)
                ->orderByDesc('accessed_at')
                ->paginate(20)
                ->through(fn(ProxyAccessHistory $h) => [
                    'action'        => $h->action,
                    'resource_type' => $h->resource_type,
                    'resource_id'   => $h->resource_id,
                    'accessed_at'   => $h->accessed_at->toIso8601String(),
                    'accessed_at_human' => $h->accessed_at->diffForHumans(),
                ]);

            return response()->json([
                'success' => true,
                'proxy'   => [
                    'id'           => $proxy->id,
                    'proxy_email'  => $proxy->proxy_email,
                    'relationship' => $proxy->relationship,
                    'status'       => $proxy->status,
                ],
                'history' => $history,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProxyAccessController@history failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Something went wrong.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Public: Accept invitation via token (email link)
    // POST /api/proxy/accept/{token}
    // -------------------------------------------------------------------------
    public function accept(Request $request, string $token): JsonResponse
    {
        try {
            $proxy = ProxyAccess::where('invitation_token', $token)->first();

            if (!$proxy) {
                return response()->json(['success' => false, 'message' => 'Invalid invitation link.'], 404);
            }

            if (!$proxy->isPending()) {
                return response()->json(['success' => false, 'message' => 'This invitation has already been accepted or is no longer valid.'], 422);
            }

            if ($proxy->isTokenExpired()) {
                $proxy->update(['status' => 'expired']);
                return response()->json(['success' => false, 'message' => 'This invitation link has expired. Please ask the patient to send a new invitation.'], 422);
            }

            // Find or create the proxy user account
            $proxyUser = User::where('email', $proxy->proxy_email)->first();

            if (!$proxyUser) {
                // Create a new user account for the proxy
                $proxyUser = User::create([
                    'name'      => explode('@', $proxy->proxy_email)[0],
                    'email'     => $proxy->proxy_email,
                    'password'  => Hash::make(Str::random(16)), // random; they should use magic link / password reset
                    'role'      => 'User',
                    'is_active' => true,
                ]);
            }

            $proxy->update([
                'proxy_user_id'    => $proxyUser->id,
                'status'           => 'active',
                'accepted_at'      => now(),
                'invitation_token' => null,
                'token_expires_at' => null,
            ]);

            Log::channel('auth')->info('Proxy invitation accepted', [
                'proxy_access_id' => $proxy->id,
                'proxy_user_id'   => $proxyUser->id,
                'patient_user_id' => $proxy->patient_user_id,
            ]);

            return response()->json([
                'success'  => true,
                'message'  => 'Proxy access accepted successfully. You can now log in to view the patient\'s health records.',
                'is_new_user' => $proxyUser->wasRecentlyCreated,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProxyAccessController@accept failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Something went wrong.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Proxy: List patients the proxy can access
    // GET /api/proxy/my-access
    // -------------------------------------------------------------------------
    public function myAccess(Request $request): JsonResponse
    {
        try {
            $proxyUser = auth()->user();

            $accesses = ProxyAccess::where('proxy_user_id', $proxyUser->id)
                ->where('status', 'active')
                ->with('patientUser')
                ->get()
                ->map(fn(ProxyAccess $p) => [
                    'proxy_access_id'  => $p->id,
                    'patient_user_id'  => $p->patient_user_id,
                    'patient_name'     => $p->patientUser?->name ?? $p->patientUser?->email,
                    'relationship'     => $p->relationship,
                    'access_level'     => $p->access_level,
                    'accepted_at'      => $p->accepted_at?->toIso8601String(),
                ]);

            return response()->json(['success' => true, 'accesses' => $accesses]);
        } catch (\Throwable $e) {
            Log::error('ProxyAccessController@myAccess failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Something went wrong.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Proxy: Switch context to a patient's data
    // POST /api/proxy/switch-patient
    // Issues a new JWT token with the patient's context embedded in the claims.
    // -------------------------------------------------------------------------
    public function switchPatient(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'patient_user_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()], 422);
            }

            $proxyUser     = auth()->user();
            $patientUserId = (int) $request->patient_user_id;

            // Verify active proxy access exists
            $proxyAccess = ProxyAccess::where('proxy_user_id', $proxyUser->id)
                ->where('patient_user_id', $patientUserId)
                ->where('status', 'active')
                ->first();

            if (!$proxyAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have active proxy access to this patient.',
                ], 403);
            }

            $patientUser = User::find($patientUserId);
            if (!$patientUser) {
                return response()->json(['success' => false, 'message' => 'Patient account not found.'], 404);
            }

            // Fetch the patient's IDs directly from the users table (getAllPatientIds)
            // instead of getActivePatientIds() which cross-checks the AHCS DB and
            // would return [] if the patient record is missing or soft-deleted there.
            $patientIds = $patientUser->getAllPatientIds();

            $caseIds = !empty($patientIds)
                ? AhcsCase::whereIn('patient_id', $patientIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->toArray()
                : [];

            // ── Issue a new JWT with proxy context embedded in claims ─────
            $oldToken   = JWTAuth::parseToken()->getToken();
            $oldPayload = JWTAuth::parseToken()->getPayload();
            $oldJwtId   = $oldPayload->get('jti');

            // Inject proxy context into the proxy user model so getJWTCustomClaims picks it up
            $freshProxy = User::find($proxyUser->id);
            $freshProxy->jwtProxyContext = [
                'proxy_access_id' => $proxyAccess->id,
                'patient_user_id' => $patientUserId,
                'patient_ids'     => $patientIds,
                'case_ids'        => $caseIds,
                'access_level'    => $proxyAccess->access_level,
            ];

            $newToken   = JWTAuth::fromUser($freshProxy);
            $newPayload = JWTAuth::manager()->decode(new Token($newToken));
            $newJwtId   = $newPayload->get('jti');

            // Invalidate old token and update the session record
            JWTAuth::setToken($oldToken)->invalidate();

            UserSession::where('user_id', $proxyUser->id)
                ->where('jwt_id', $oldJwtId)
                ->where('is_active', 1)
                ->update([
                    'jwt_id'        => $newJwtId,
                    'token'         => $newToken,
                    'ip_address'    => $request->ip(),
                    'user_agent'    => $request->userAgent(),
                    'last_activity' => now(),
                    'updated_at'    => now(),
                ]);

            Log::channel('auth')->info('Proxy switched patient context — new token issued', [
                'proxy_user_id'   => $proxyUser->id,
                'patient_user_id' => $patientUserId,
                'patient_ids'     => $patientIds,
                'case_count'      => count($caseIds),
            ]);

            return response()->json([
                'success'        => true,
                'message'        => 'Switched to patient context successfully.',
                'token'          => $newToken,
                'patient_name'   => $patientUser->name ?? $patientUser->email,
                'access_level'   => $proxyAccess->access_level,
                'patient_ids'    => $patientIds,
                'case_ids'       => $caseIds,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProxyAccessController@switchPatient failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Something went wrong.'], 500);
        }
    }
}
