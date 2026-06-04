<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AhcsPatient;
use App\Models\AhcsCase;
use Illuminate\Http\Request;
use App\Models\UserSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Token;

class AuthApiController extends Controller
{
    public function login(Request $request)
    {
        try {
            Log::channel('auth')->info('Login API hit', [
                'email' => $request->email
            ]);

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 422);
            }

            $credentials = $request->only('email', 'password');

            Log::channel('auth')->info('Attempting login', [
                'email' => $credentials['email']
            ]);

            if (!$token = Auth::guard('api')->attempt($credentials)) {
                Log::channel('auth')->warning('Invalid credentials', [
                    'email' => $credentials['email']
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // ✅ Get logged-in user
            $user = Auth::guard('api')->user();

            // ── Patient active check ──────────────────────────────────────────
            // Reject login if ALL linked patients are soft-deleted in AhcsPatient.
            $patientIds = $user->getAllPatientIds();

            if (!empty($patientIds)) {
                $activePatientExists = AhcsPatient::whereIn('id', $patientIds)
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$activePatientExists) {
                    Auth::guard('api')->logout();

                    Log::channel('auth')->warning('Login blocked: all linked patients are deleted', [
                        'user_id'     => $user->id,
                        'email'       => $user->email,
                        'patient_ids' => $patientIds,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Your account is no longer active. Please contact support.',
                    ], 403);
                }
            }

            // ── Invalidate any existing active sessions ───────────────────────
            $activeSessions = UserSession::where('user_id', $user->id)
                ->where('is_active', 1)
                ->get();

            foreach ($activeSessions as $session) {
                try {
                    JWTAuth::setToken($session->token)->invalidate();
                } catch (\Throwable) {
                    // Token may already be expired — safe to ignore.
                }
            }

            UserSession::where('user_id', $user->id)
                ->where('is_active', 1)
                ->update([
                    'is_active'     => 0,
                    'updated_at'    => now(),
                ]);

            Log::channel('auth')->info('Previous active sessions invalidated', [
                'user_id'       => $user->id,
                'session_count' => $activeSessions->count(),
            ]);

            // ── Create new session ────────────────────────────────────────────
            $user->forceFill(['last_login_at' => now()])->save();

            $payload = JWTAuth::setToken($token)->getPayload();
            $jwtId   = $payload->get('jti');

            UserSession::create([
                'user_id'       => $user->id,
                'jwt_id'        => $jwtId,
                'token'         => $token,
                'ip_address'    => $request->ip(),
                'user_agent'    => $request->userAgent(),
                'is_active'     => 1,
                'last_activity' => now(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            Log::channel('auth')->info('Login successful', [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('auth')->error('Login failed', [
                'email' => $request->email ?? null,
                'message' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $token = $request->bearerToken();
            UserSession::where('token', $token)->update([
                'is_active' => 0,
            ]);

            $user = Auth::guard('api')->user();
            Log::channel('auth')->info('Logout API hit', [
                'user_id' => $user->id ?? null
            ]);
            // invalidate current token
            Auth::guard('api')->logout();
            Log::channel('auth')->info('Logout successful', [
                'user_id' => $user->id ?? null
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ]);
        } catch (\Exception $e) {
            Log::channel('auth')->error('Logout failed', [
                'user_id' => Auth::guard('api')->id(),
                'message' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout'
            ], 500);
        }
    }

    public function refreshToken(Request $request)
    {
        try {
            $oldToken = JWTAuth::parseToken()->getToken();

            if (!$oldToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token not provided'
                ], 401);
            }

            $oldPayload = JWTAuth::parseToken()->getPayload();
            $oldJwtId   = $oldPayload->get('jti');

            $user = Auth::guard('api')->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated user'
                ], 401);
            }

            $freshUser = User::find($user->id);
            if (!$freshUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 401);
            }

            // Resolve case_id: prefer the request param, fall back to the old token claim.
            $caseId     = $request->input('case_id') ?? $oldPayload->get('case_id');
            $patientIds = $freshUser->getAllPatientIds();

            if (empty($caseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case ID is required.',
                ], 422);
            }

            $caseRecord = AhcsCase::where('id', $caseId)
                ->whereIn('patient_id', $patientIds)
                ->first(['patient_id']);

            if (!$caseRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Case ID for this patient.',
                ], 422);
            }

            $patientId = $caseRecord->patient_id;
            $patient   = AhcsPatient::find($patientId);

            $patientDetails = $patient ? [
                'id'         => $patient->id,
                'first_name' => $patient->first_name,
                'last_name'  => $patient->last_name,
                'full_name'  => $patient->patient_name,
                'dob'        => $patient->dob,
                'email'      => $patient->email,
                'home_phone' => $patient->cell_no ?? $patient->home_ph,
                'address1'   => $patient->address1,
            ] : null;

            // Inject into the User model so getJWTCustomClaims() embeds them in the token.
            $freshUser->jwtCaseId         = (int) $caseId;
            $freshUser->jwtPatientDetails = $patientDetails;

            $newToken = JWTAuth::fromUser($freshUser);
            JWTAuth::setToken($oldToken)->invalidate();
            $newPayload = JWTAuth::manager()->decode(new Token($newToken));
            $newJwtId   = $newPayload->get('jti');

            UserSession::where('user_id', $user->id)
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

            Log::channel('auth')->info('Token refreshed successfully', [
                'user_id'    => $user->id,
                'case_id'    => $caseId,
                'patient_id' => $patientId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'token'   => $newToken,
            ], 200);
        } catch (\Throwable $e) {
            Log::channel('auth')->error('Refresh token failed', [
                'user_id' => Auth::guard('api')->id(),
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token'
            ], 401);
        }
    }

    public function magicLinkVerify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $exists = User::hasPatientId((int) $request->patient_id)->exists();

        return response()->json([
            'success' => true,
            'flag' => $exists ? 'exist' : 'not_exists',
        ], 200);
    }
}
