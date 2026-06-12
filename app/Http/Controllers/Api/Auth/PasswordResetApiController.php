<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetApiController extends Controller
{
    // ─── Constants ────────────────────────────────────────────────────────────

    /** Max forgot-password attempts per email per window */
    private const FORGOT_MAX_ATTEMPTS = 3;

    /** Rate-limit decay window in seconds (1.5 minutes) */
    private const FORGOT_DECAY_SECONDS = 90;

    /** Max reset attempts per token per window (brute-force guard) */
    private const RESET_MAX_ATTEMPTS = 5;

    /** Reset rate-limit decay window in seconds (1.5 minutes) */
    private const RESET_DECAY_SECONDS = 90;

    // ─── Forgot Password ──────────────────────────────────────────────────────

    /**
     * POST /api/password/forgot
     *
     * Accepts the patient's email address, generates a cryptographically secure
     * reset token (stored hashed in `password_reset_tokens`), and emails a
     * one-time reset link to the patient.
     *
     * To prevent user-enumeration attacks the response is identical whether or
     * not the email exists in the database.
     *
     * Request Body:
     *   { "email": "patient@example.com" }
     *
     * Response 200:
     *   { "status": true, "message": "If that email is registered, a reset link has been sent." }
     *
     * Response 422:
     *   { "status": false, "message": "Validation error message." }
     *
     * Response 429:
     *   { "status": false, "message": "Too many requests. Please try again in X seconds." }
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            // ── Validate ──────────────────────────────────────────────────────
            $request->validate([
                'email' => ['required', 'email', 'max:255'],
            ]);

            $email = $request->input('email');


            // ── Rate-limit per email address ──────────────────────────────────
            $rateLimitKey = 'password.forgot.' . sha1($email);

            if (RateLimiter::tooManyAttempts($rateLimitKey, self::FORGOT_MAX_ATTEMPTS)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);

                Log::channel('password_reset')->warning('Forgot-password rate limit hit', [
                    'email'            => $email,
                    'ip'               => $request->ip(),
                    'retry_after_secs' => $seconds,
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => "Too many password-reset requests. Please try again in {$seconds} seconds.",
                ], 429);
            }

            RateLimiter::hit($rateLimitKey, self::FORGOT_DECAY_SECONDS);

            Log::channel('password_reset')->info('Forgot-password request received', [
                'email' => $email,
                'ip'    => $request->ip(),
            ]);

            // ── Look up user (no early return to avoid enumeration) ───────────
            $user = User::where('email', $email)->first();

        
            if ($user) {
                // Delete any stale token for this email so the table stays tidy
                DB::table('password_reset_tokens')->where('email', $email)->delete();

                // Generate a plain-text token (64 random bytes → 128 hex chars)
                $plainToken = Str::random(64);

                // Store the hashed version — Hash::check() used on verify
                DB::table('password_reset_tokens')->insert([
                    'email'      => $email,
                    'token'      => Hash::make($plainToken),
                    'created_at' => now(),
                ]);

                // Build the frontend reset URL
                // The frontend reads `token` + `email` from the query string
                $resetUrl = rtrim(config('app.frontend_url', 'https://app.advantagehcs.com'), '/')
                    . '/reset-password'
                    . '?token=' . urlencode($plainToken)
                    . '&email=' . urlencode($email);

                $expiresInMinutes = (int) config('auth.passwords.users.expire', 60);

                Mail::to($email)->send(
                    new PasswordResetMail(
                        $user->name ?? 'Patient',
                        $resetUrl,
                        $expiresInMinutes,
                    )
                );
                    

                Log::channel('password_reset')->info('Password-reset email dispatched', [
                    'user_id' => $user->id,
                    'email'   => $email,
                ]);
            } else {
                // Log silently; do NOT reveal to the caller that the email is unknown
                Log::channel('password_reset')->info('Forgot-password: email not found (no action taken)', [
                    'email' => $email,
                    'ip'    => $request->ip(),
                ]);
            }

            // Always return the same response (anti-enumeration)
            return response()->json([
                'status'  => true,
                'message' => 'If that email is registered, a password reset link has been sent. Please check your inbox.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->validator->errors()->first(),
            ], 422);

        } catch (\Throwable $e) {
            Log::channel('password_reset')->error('Forgot-password error', [
                'email'   => $request->input('email') ?? null,
                'ip'      => $request->ip(),
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }

    // ─── Reset Password ───────────────────────────────────────────────────────

    /**
     * POST /api/password/reset
     *
     * Verifies the one-time reset token and updates the patient's password.
     *
     * Security measures applied:
     *   - Token is verified with constant-time Hash::check (bcrypt)
     *   - Token expires after the minutes configured in config/auth.php (default 60)
     *   - Token is deleted immediately after first successful use (single-use)
     *   - All active JWT sessions are invalidated after reset (force re-login)
     *   - Rate-limited to prevent brute-force guessing of tokens
     *
     * Request Body:
     *   {
     *     "token"                : "<plain token from email link>",
     *     "email"                : "patient@example.com",
     *     "password"             : "NewP@ssw0rd!",
     *     "password_confirmation": "NewP@ssw0rd!"
     *   }
     *
     * Response 200:
     *   { "status": true, "message": "Password has been reset successfully. Please log in with your new password." }
     *
     * Response 400:
     *   { "status": false, "message": "Invalid or expired reset link." }
     *
     * Response 422:
     *   { "status": false, "message": "Validation error message." }
     *
     * Response 429:
     *   { "status": false, "message": "Too many attempts. Please try again in X seconds." }
     */
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            // ── Validate ──────────────────────────────────────────────────────
            $request->validate([
                'token'    => ['required', 'string'],
                'email'    => ['required', 'email', 'max:255'],
                'password' => [
                    'required',
                    'string',
                    'confirmed',          // requires password_confirmation field
                    PasswordRule::min(8)
                        ->mixedCase()     // uppercase + lowercase
                        ->letters()       // at least one letter
                        ->numbers()       // at least one number
                        ->symbols()       // at least one special character
                ],
            ]);

            $email      = strtolower(trim($request->input('email')));
            $plainToken = $request->input('token');

            // ── Rate-limit per email to prevent token brute-force ─────────────
            $rateLimitKey = 'password.reset.' . sha1($email);

            if (RateLimiter::tooManyAttempts($rateLimitKey, self::RESET_MAX_ATTEMPTS)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);

                Log::channel('password_reset')->warning('Reset-password rate limit hit', [
                    'email'            => $email,
                    'ip'               => $request->ip(),
                    'retry_after_secs' => $seconds,
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => "Too many attempts. Please try again in {$seconds} seconds.",
                ], 429);
            }

            RateLimiter::hit($rateLimitKey, self::RESET_DECAY_SECONDS);

            Log::channel('password_reset')->info('Reset-password attempt', [
                'email' => $email,
                'ip'    => $request->ip(),
            ]);

            // ── Fetch stored token record ─────────────────────────────────────
            $record = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->first();

            if (!$record) {
                Log::channel('password_reset')->warning('Reset-password: no token record found', [
                    'email' => $email,
                    'ip'    => $request->ip(),
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid or expired password reset link.',
                ], 400);
            }

            // ── Check expiry ──────────────────────────────────────────────────
            $expireMinutes = (int) config('auth.passwords.users.expire', 60);
            $tokenAge      = now()->diffInMinutes($record->created_at);

            if ($tokenAge >= $expireMinutes) {
                // Clean up the expired record
                DB::table('password_reset_tokens')->where('email', $email)->delete();

                Log::channel('password_reset')->warning('Reset-password: token expired', [
                    'email'      => $email,
                    'token_age'  => $tokenAge,
                    'ip'         => $request->ip(),
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'This password reset link has expired. Please request a new one.',
                ], 400);
            }

            // ── Constant-time token verification ─────────────────────────────
            if (!Hash::check($plainToken, $record->token)) {
                Log::channel('password_reset')->warning('Reset-password: token mismatch', [
                    'email' => $email,
                    'ip'    => $request->ip(),
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid or expired password reset link.',
                ], 400);
            }

            // ── Find the user ─────────────────────────────────────────────────
            $user = User::where('email', $email)->first();

            if (!$user) {
                // Token existed but user was deleted — tidy up and reject
                DB::table('password_reset_tokens')->where('email', $email)->delete();

                Log::channel('password_reset')->warning('Reset-password: user not found after token check', [
                    'email' => $email,
                    'ip'    => $request->ip(),
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid or expired password reset link.',
                ], 400);
            }

            // ── Prevent reuse of the same password ───────────────────────────
            if (Hash::check($request->input('password'), $user->password)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Your new password must be different from your current password.',
                ], 422);
            }

            DB::beginTransaction();

            try {
                // ── Update the password ───────────────────────────────────────
                $user->password = Hash::make($request->input('password'));
                $user->save();

                // ── Consume (delete) the token immediately (single-use) ───────
                DB::table('password_reset_tokens')->where('email', $email)->delete();

                // ── Invalidate ALL active JWT sessions for this user ──────────
                // Forces the patient to log in again with the new password
                UserSession::where('user_id', $user->id)
                    ->where('is_active', 1)
                    ->update([
                        'is_active'     => 0,
                        'updated_at'    => now(),
                    ]);

                DB::commit();

            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            // ── Clear the rate-limit for this email on success ────────────────
            RateLimiter::clear($rateLimitKey);

            Log::channel('password_reset')->info('Password reset successful', [
                'user_id' => $user->id,
                'email'   => $email,
                'ip'      => $request->ip(),
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Password has been reset successfully. Please log in with your new password.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->validator->errors()->first(),
            ], 422);

        } catch (\Throwable $e) {
            Log::channel('password_reset')->error('Reset-password error', [
                'email'   => $request->input('email') ?? null,
                'ip'      => $request->ip(),
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }

    // ─── Validate Token (optional helper for frontend) ────────────────────────

    /**
     * GET /api/password/validate-token?token={token}&email={email}
     *
     * Allows the frontend reset-password page to check if a token is still
     * valid BEFORE showing the new-password form (improves UX — the user sees
     * an "expired" message immediately instead of after filling the form).
     *
     * This endpoint does NOT consume the token.
     *
     * Response 200: { "status": true,  "message": "Token is valid." }
     * Response 400: { "status": false, "message": "Invalid or expired link." }
     */
    public function validateToken(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'token' => ['required', 'string'],
                'email' => ['required', 'email', 'max:255'],
            ]);

            $email      = strtolower(trim($request->input('email')));
            $plainToken = $request->input('token');

            // Rate-limit this endpoint too (same bucket as reset)
            $rateLimitKey = 'password.reset.' . sha1($email);

            if (RateLimiter::tooManyAttempts($rateLimitKey, self::RESET_MAX_ATTEMPTS)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                return response()->json([
                    'status'  => false,
                    'message' => "Too many attempts. Please try again in {$seconds} seconds.",
                ], 429);
            }

            RateLimiter::hit($rateLimitKey, self::RESET_DECAY_SECONDS);

            $record = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->first();

            if (!$record) {
                return response()->json(['status' => false, 'message' => 'Invalid or expired reset link.'], 400);
            }

            $expireMinutes = (int) config('auth.passwords.users.expire', 60);
            if (now()->diffInMinutes($record->created_at) >= $expireMinutes) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                return response()->json(['status' => false, 'message' => 'This reset link has expired. Please request a new one.'], 400);
            }

            if (!Hash::check($plainToken, $record->token)) {
                return response()->json(['status' => false, 'message' => 'Invalid or expired reset link.'], 400);
            }

            return response()->json(['status' => true, 'message' => 'Token is valid.'], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => false, 'message' => $e->validator->errors()->first()], 422);
        } catch (\Throwable $e) {
            Log::channel('password_reset')->error('Validate-token error', [
                'email'   => $request->input('email') ?? null,
                'message' => $e->getMessage(),
            ]);
            return response()->json(['status' => false, 'message' => 'Something went wrong.'], 500);
        }
    }
}
