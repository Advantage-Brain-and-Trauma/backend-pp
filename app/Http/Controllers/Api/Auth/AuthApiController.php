<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
            $user->forceFill([
                'last_login_at' => now(),
            ])->save();
            $payload = JWTAuth::setToken($token)->getPayload();
            $jwtId = $payload->get('jti');

            UserSession::create([
                'user_id' => $user->id,
                'jwt_id'       => $jwtId,
                'token' => $token,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'is_active' => 1,
                'last_activity' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::channel('auth')->info('Login successful', [
                'user_id' => $user->id,
                'email' => $user->email
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
            $oldJwtId = $oldPayload->get('jti');
            $activeCaseId = $oldPayload->get('case_id');

            $user = Auth::guard('api')->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated user'
                ], 401);
            }

            $freshUser = User::with('patient')->find($user->id);
            if (!$freshUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 401);
            }

            $claimOverrides = [];
            if (!is_null($activeCaseId)) {
                $claimOverrides['case_id'] = $activeCaseId;
            }

            // Force claim generation via User::getJWTCustomClaims()
            $newToken = JWTAuth::fromUser($freshUser, $claimOverrides);
            JWTAuth::setToken($oldToken)->invalidate();
            $newPayload = JWTAuth::manager()->decode(new Token($newToken));
            $newJwtId = $newPayload->get('jti');

            UserSession::where('user_id', $user->id)
                ->where('jwt_id', $oldJwtId)
                ->where('is_active', 1)
                ->update([
                    'jwt_id' => $newJwtId,
                    'token' => $newToken,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'last_activity' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'token' => $newToken,
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
