<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AhcsPatient;
use App\Models\ProxyAccess;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class DirectEmailLoginController extends Controller
{
    public function loginByEmail(Request $request)
    {
        try {
            Log::channel('auth')->info('Direct email login API hit', [
                'email' => $request->email
            ]);

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                Log::channel('auth')->warning('Direct email login: no user found', [
                    'email' => $request->email
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $token = Auth::guard('api')->login($user);

            // ── Proxy account revoked check ───────────────────────────────────
            if ($user->is_proxy_account) {
                $hasActiveProxy = ProxyAccess::where('proxy_user_id', $user->id)
                    ->where('status', 'active')
                    ->exists();

                if (!$hasActiveProxy) {
                    Auth::guard('api')->logout();

                    Log::channel('auth')->warning('Direct email login blocked: proxy access has been revoked', [
                        'user_id' => $user->id,
                        'email'   => $user->email,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Your proxy access has been revoked. Please contact the patient.',
                    ], 403);
                }
            }

            // ── Patient active check ──────────────────────────────────────────
            $patientIds = $user->getAllPatientIds();

            if (!empty($patientIds)) {
                $activePatientExists = AhcsPatient::whereIn('id', $patientIds)
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$activePatientExists) {
                    Auth::guard('api')->logout();

                    Log::channel('auth')->warning('Direct email login blocked: all linked patients are deleted', [
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

            Log::channel('auth')->info('Direct email login successful', [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('auth')->error('Direct email login failed', [
                'email' => $request->email ?? null,
                'message' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }
}
