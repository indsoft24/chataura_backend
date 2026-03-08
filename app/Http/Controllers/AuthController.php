<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Mail\EmailOtpMail;
use App\Models\AdminSetting;
use App\Models\ReferralHistory;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private JwtService $jwtService
    ) {}

    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'phone' => 'required_without:email|string|unique:users,phone',
                'email' => 'required_without:phone|email|unique:users,email',
                'password' => 'required|string|min:6',
                'display_name' => 'nullable|string|max:255',
                'invite_code' => 'nullable|string|exists:users,invite_code',
                'referral_code' => 'nullable|string|exists:users,invite_code',
            ]);

            $referralCode = $validated['referral_code'] ?? $validated['invite_code'] ?? null;

            // Generate invite code
            $inviteCode = Str::random(8);
            while (User::where('invite_code', $inviteCode)->exists()) {
                $inviteCode = Str::random(8);
            }

            // Find referrer if referral/invite code provided
            $invitedBy = null;
            $referrer = null;
            if ($referralCode) {
                $referrer = User::where('invite_code', $referralCode)->first();
                if ($referrer) {
                    $invitedBy = $referrer->id;
                }
            }

            $displayName = $validated['display_name'] ?? ($validated['phone'] ?? $validated['email'] ?? 'User');

            $user = User::create([
                'name' => $displayName,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'password' => Hash::make($validated['password']),
                'display_name' => $displayName,
                'invite_code' => $inviteCode,
                'invited_by' => $invitedBy,
                'level' => 1,
                'exp' => 0,
                'coin_balance' => 0,
                'referral_balance' => 0,
            ]);

            // Refer & Earn: credit referral_balance and log in referral_history
            if ($referrer) {
                $settings = AdminSetting::get();
                $rewardReferrer = (int) $settings->referral_reward_referrer;
                $rewardReferee = (int) $settings->referral_reward_referee;

                DB::transaction(function () use ($referrer, $user, $rewardReferrer, $rewardReferee) {
                    $referrer->increment('referral_balance', $rewardReferrer);
                    $user->increment('referral_balance', $rewardReferee);
                    ReferralHistory::create([
                        'referrer_id' => $referrer->id,
                        'referee_id' => $user->id,
                        'referrer_amount' => $rewardReferrer,
                        'referee_amount' => $rewardReferee,
                    ]);
                });
            }

            $accessToken = $this->jwtService->generateAccessToken($user);
            $refreshToken = $this->jwtService->generateRefreshToken($user);

            return ApiResponse::success([
                'user' => $this->userForApi($user),
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => config('auth.access_token_expiry', 3600),
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Registration database error: ' . $e->getMessage());
            return ApiResponse::error('REGISTRATION_FAILED', config('app.debug') ? $e->getMessage() : 'Database error occurred', 500);
        } catch (\Exception $e) {
            \Log::error('Registration error: ' . $e->getMessage());
            return ApiResponse::error('REGISTRATION_FAILED', config('app.debug') ? $e->getMessage() : 'An error occurred during registration', 500);
        }
    }

    /**
     * Login user.
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'phone' => 'required_without:email|string',
                'email' => 'required_without:phone|email',
                'password' => 'required|string',
            ]);

            $user = User::where(function ($query) use ($validated) {
                if (isset($validated['phone'])) {
                    $query->where('phone', $validated['phone']);
                }
                if (isset($validated['email'])) {
                    $query->orWhere('email', $validated['email']);
                }
            })->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return ApiResponse::error('INVALID_CREDENTIALS', 'Invalid phone/email or password', 401);
            }

            $accessToken = $this->jwtService->generateAccessToken($user);
            $refreshToken = $this->jwtService->generateRefreshToken($user);

            return ApiResponse::success([
                'user' => $this->userForApi($user),
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => config('auth.access_token_expiry', 3600),
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('LOGIN_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * Refresh access token.
     */
    public function refresh(Request $request)
    {
        try {
            $validated = $request->validate([
                'refresh_token' => 'required|string',
            ]);

            $user = $this->jwtService->verifyRefreshToken($validated['refresh_token']);

            if (!$user) {
                return ApiResponse::error('INVALID_REFRESH_TOKEN', 'Invalid or expired refresh token', 401);
            }

            $accessToken = $this->jwtService->generateAccessToken($user);
            $refreshToken = $this->jwtService->generateRefreshToken($user);

            return ApiResponse::success([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => config('auth.access_token_expiry', 3600),
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('REFRESH_FAILED', $e->getMessage(), 500);
        }
    }

    /**
     * Sign in with Google (ID token from Android/iOS Google Sign-In SDK).
     * POST /api/v1/auth/google
     * Body: { "id_token": "eyJhbGciOiJSUzI1NiIs..." }
     */
    public function google(Request $request)
    {
        try {
            $validated = $request->validate([
                'id_token' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $payload = $this->verifyGoogleIdToken($validated['id_token']);
        if (!$payload) {
            return ApiResponse::error('INVALID_ID_TOKEN', 'Invalid or expired Google ID token', 401);
        }

        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? $payload['email'] ?? 'User';
        $picture = $payload['picture'] ?? null;

        if (empty($email)) {
            return ApiResponse::error('INVALID_ID_TOKEN', 'Google token did not contain email', 401);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $inviteCode = Str::random(8);
            while (User::where('invite_code', $inviteCode)->exists()) {
                $inviteCode = Str::random(8);
            }
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'display_name' => $name,
                'avatar_url' => $picture,
                'password' => Hash::make(Str::random(32)),
                'invite_code' => $inviteCode,
                'level' => 1,
                'exp' => 0,
                'coin_balance' => 0,
            ]);
            $user->email_verified_at = now();
            $user->save();
        } else {
            // Optional: update name/avatar from Google if changed
            $updated = false;
            if ($user->display_name !== $name || $user->name !== $name) {
                $user->name = $name;
                $user->display_name = $name;
                $updated = true;
            }
            if ($picture !== null && $user->avatar_url !== $picture) {
                $user->avatar_url = $picture;
                $updated = true;
            }
            if ($updated) {
                $user->save();
            }
        }

        $accessToken = $this->jwtService->generateAccessToken($user);
        $refreshToken = $this->jwtService->generateRefreshToken($user);

        return ApiResponse::success([
            'user' => $this->userForApi($user),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => config('auth.access_token_expiry', 3600),
        ]);
    }

    /**
     * User payload for API (auth and user responses). Ensures email_verified_at is ISO string or null for Android.
     *
     * @return array<string, mixed>
     */
    private function userForApi(User $user): array
    {
        $data = $user->makeHidden(['password', 'remember_token', 'fcm_token'])->toArray();
        $data['email_verified_at'] = $user->email_verified_at?->toIso8601String();
        return $data;
    }

    /**
     * Verify Google ID token via tokeninfo endpoint; returns payload or null.
     *
     * @return array<string, mixed>|null
     */
    private function verifyGoogleIdToken(string $idToken): ?array
    {
        $clientId = config('services.google.client_id');
        if (empty($clientId)) {
            return null;
        }
        try {
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);
            if (!$response->successful()) {
                return null;
            }
            $payload = $response->json();
            if (!is_array($payload) || ($payload['aud'] ?? '') !== $clientId) {
                return null;
            }
            return $payload;
        } catch (\Throwable $e) {
            \Log::warning('Google ID token verification failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Send 6-digit OTP to the authenticated user's email (after email registration).
     * POST /api/v1/auth/send-email-otp — auth required, no body.
     */
    public function sendEmailOtp(Request $request)
    {
        $user = $request->user();

        if (empty($user->email)) {
            return ApiResponse::error('NO_EMAIL', 'User has no email address', 400);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = 'email_otp:email_verification:' . $user->id;
        Cache::put($cacheKey, $otp, now()->addMinutes(15));

        try {
            Mail::to($user->email)->send(new EmailOtpMail($otp, config('app.name')));
        } catch (\Throwable $e) {
            \Log::error('Send email OTP failed: ' . $e->getMessage());
            return ApiResponse::error('EMAIL_SEND_FAILED', 'Failed to send verification email', 500);
        }

        return ApiResponse::success(null);
    }

    /**
     * Verify email OTP and mark email as verified.
     * POST /api/v1/auth/verify-email-otp — auth required, body: { "otp": "123456" }.
     */
    public function verifyEmailOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'otp' => 'required|string|size:6',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $user = $request->user();
        $cacheKey = 'email_otp:email_verification:' . $user->id;
        $storedOtp = Cache::get($cacheKey);

        if ($storedOtp === null || !hash_equals((string) $storedOtp, (string) $validated['otp'])) {
            return ApiResponse::error('INVALID_OTP', 'Invalid or expired OTP', 400);
        }

        Cache::forget($cacheKey);
        $user->email_verified_at = now();
        $user->save();

        return ApiResponse::success(null);
    }

    /**
     * Forgot password: send 6-digit OTP to the given email (unauthenticated).
     * POST /api/v1/auth/forgot-password
     * Body: { "email": "user@example.com" }
     * Rate-limit: max 3 requests per 10 minutes per email.
     * Returns success even if email not found (prevent enumeration).
     */
    public function forgotPassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $email = strtolower($validated['email']);
        $rateKey = 'forgot_password_rate:' . $email;
        $attempts = (int) Cache::get($rateKey, 0);
        if ($attempts >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please try again in 10 minutes.',
            ], 429);
        }
        Cache::put($rateKey, $attempts + 1, now()->addMinutes(10));

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'OTP sent to your email',
            ]);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpKey = 'email_otp:forgot_password:' . $email;
        Cache::put($otpKey, $otp, now()->addMinutes(10));

        try {
            Mail::to($user->email)->send(new EmailOtpMail($otp, config('app.name')));
        } catch (\Throwable $e) {
            \Log::error('Forgot password email failed: ' . $e->getMessage());
            return ApiResponse::error('EMAIL_SEND_FAILED', 'Failed to send OTP email', 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your email',
        ]);
    }

    /**
     * Reset password with OTP (unauthenticated).
     * POST /api/v1/auth/reset-password
     * Body: { "email": "...", "otp": "123456", "password": "newPassword123" }
     */
    public function resetPassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'otp' => 'required|string|size:6',
                'password' => 'required|string|min:6',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $email = strtolower($validated['email']);
        $otpKey = 'email_otp:forgot_password:' . $email;
        $storedOtp = Cache::get($otpKey);

        if ($storedOtp === null || !hash_equals((string) $storedOtp, (string) $validated['otp'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP',
            ], 400);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP',
            ], 400);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();
        Cache::forget($otpKey);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully',
        ]);
    }

    /**
     * Change password (step 1): verify current password and send OTP to registered email.
     * POST /api/v1/auth/change-password/request (auth required)
     * Body: { "current_password": "...", "new_password": "..." }
     */
    public function changePasswordRequest(Request $request)
    {
        try {
            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $user = $request->user();
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        if (empty($user->email)) {
            return ApiResponse::error('NO_EMAIL', 'User has no email address', 400);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpKey = 'email_otp:change_password:' . $user->id;
        $pendingKey = 'change_password_pending:' . $user->id;
        Cache::put($otpKey, $otp, now()->addMinutes(10));
        Cache::put($pendingKey, Hash::make($validated['new_password']), now()->addMinutes(10));

        try {
            Mail::to($user->email)->send(new EmailOtpMail($otp, config('app.name')));
        } catch (\Throwable $e) {
            \Log::error('Change password OTP email failed: ' . $e->getMessage());
            Cache::forget($otpKey);
            Cache::forget($pendingKey);
            return ApiResponse::error('EMAIL_SEND_FAILED', 'Failed to send OTP email', 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your registered email',
        ]);
    }

    /**
     * Change password (step 2): verify OTP and apply new password.
     * POST /api/v1/auth/change-password/verify (auth required)
     * Body: { "otp": "123456", "new_password": "newPass456" }
     * Optionally invalidates all other refresh tokens (force re-login on other devices).
     */
    public function changePasswordVerify(Request $request)
    {
        try {
            $validated = $request->validate([
                'otp' => 'required|string|size:6',
                'new_password' => 'required|string|min:6',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        }

        $user = $request->user();
        $otpKey = 'email_otp:change_password:' . $user->id;
        $pendingKey = 'change_password_pending:' . $user->id;
        $storedOtp = Cache::get($otpKey);
        $pendingHash = Cache::get($pendingKey);

        if ($storedOtp === null || !hash_equals((string) $storedOtp, (string) $validated['otp'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP',
            ], 400);
        }

        if ($pendingHash === null || !Hash::check($validated['new_password'], $pendingHash)) {
            Cache::forget($otpKey);
            Cache::forget($pendingKey);
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP',
            ], 400);
        }

        $user->password = $pendingHash;
        $user->save();
        Cache::forget($otpKey);
        Cache::forget($pendingKey);

        $this->jwtService->revokeAllRefreshTokensForUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        try {
            $validated = $request->validate([
                'refresh_token' => 'required|string',
            ]);

            $this->jwtService->revokeRefreshToken($validated['refresh_token']);

            return ApiResponse::success(['message' => 'Logged out successfully']);
        } catch (ValidationException $e) {
            return ApiResponse::validationError('Validation failed', $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('LOGOUT_FAILED', $e->getMessage(), 500);
        }
    }
}

