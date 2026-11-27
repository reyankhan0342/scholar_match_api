<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ScholarMatchOtpMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email', ''))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required', 'string', 'min:8', 'confirmed',
            ],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Never issue a token before OTP verification.
        return $this->sendOtp($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email', ''))),
        ]);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (
            !$user ||
            !Hash::check($data['password'], $user->password)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        return $this->sendOtp($user);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
        ]);

        $challenge = DB::table('auth_otp_challenges')
            ->where('id', $data['challenge_id'])
            ->first();

        if (!$challenge) {
            return $this->otpError(
                'Verification session not found. Please log in again.',
                422
            );
        }

        $user = User::find($challenge->user_id);

        if (!$user) {
            return $this->otpError(
                'Verification session expired.',
                422
            );
        }

        return $this->sendOtp($user);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $result = DB::transaction(function () use ($data): array {
            $challenge = DB::table('auth_otp_challenges')
                ->where('id', $data['challenge_id'])
                ->lockForUpdate()
                ->first();

            if (!$challenge) {
                return [
                    'error' => 'Invalid or expired verification code.',
                    'status' => 422,
                ];
            }

            if (Carbon::parse($challenge->expires_at)->isPast()) {
                DB::table('auth_otp_challenges')
                    ->where('id', $challenge->id)
                    ->delete();

                return [
                    'error' => 'Verification code expired. Please log in again.',
                    'status' => 422,
                ];
            }

            if ($challenge->attempts >= 5) {
                DB::table('auth_otp_challenges')
                    ->where('id', $challenge->id)
                    ->delete();

                return [
                    'error' => 'Too many attempts. Please log in again.',
                    'status' => 429,
                ];
            }

            $user = User::find($challenge->user_id);

            if (!$user) {
                return [
                    'error' => 'Verification session is invalid.',
                    'status' => 422,
                ];
            }

            $expected = $this->otpHash(
                $data['otp'],
                $user
            );

            if (!hash_equals($challenge->code_hash, $expected)) {
                $attempts = $challenge->attempts + 1;

                DB::table('auth_otp_challenges')
                    ->where('id', $challenge->id)
                    ->update([
                        'attempts' => $attempts,
                        'updated_at' => now(),
                    ]);

                if ($attempts >= 5) {
                    DB::table('auth_otp_challenges')
                        ->where('id', $challenge->id)
                        ->delete();
                }

                return [
                    'error' => 'Invalid verification code.',
                    'status' => 422,
                ];
            }

            // Consume the OTP before issuing an API token.
            DB::table('auth_otp_challenges')
                ->where('id', $challenge->id)
                ->delete();

            if (!$user->email_verified_at) {
                $user->forceFill([
                    'email_verified_at' => now(),
                ])->save();
            }

            $token = $user->createToken(
                'scholarmatch-mobile'
            )->plainTextToken;

            return [
                'user' => $this->userData($user),
                'access_token' => $token,
            ];
        }, 3);

        if (isset($result['error'])) {
            return $this->otpError(
                $result['error'],
                $result['status']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Email verified. Login successful.',
            'data' => [
                'user' => $result['user'],
                'access_token' => $result['access_token'],
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'User profile retrieved successfully.',
            'data' => [
                'user' => $this->userData($request->user()),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
        ]);
    }

    private function sendOtp(
        User $user,
        int $status = 200
    ): JsonResponse {
        $rateKey = 'auth-otp-send:user:' . $user->id;

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return $this->otpError(
                'Too many verification emails. Try again later.',
                429
            );
        }

        $existing = DB::table('auth_otp_challenges')
            ->where('user_id', $user->id)
            ->first();

        if (
            $existing &&
            Carbon::parse($existing->sent_at)
                ->greaterThan(now()->subSeconds(60))
        ) {
            return $this->otpError(
                'Please wait 60 seconds before requesting another code.',
                429
            );
        }

        RateLimiter::hit($rateKey, 3600);

        $otp = str_pad(
            (string) random_int(0, 999999),
            6,
            '0',
            STR_PAD_LEFT
        );

        $id = $existing?->id ?? (string) Str::uuid();

        $record = [
            'code_hash' => $this->otpHash($otp, $user),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'sent_at' => now(),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('auth_otp_challenges')
                ->where('user_id', $user->id)
                ->update($record);
        } else {
            DB::table('auth_otp_challenges')->insert([
                'id' => $id,
                'user_id' => $user->id,
                ...$record,
                'created_at' => now(),
            ]);
        }

        try {
            Mail::to($user->email)->send(
                new ScholarMatchOtpMail($otp)
            );
        } catch (Throwable $exception) {
            report($exception);

            DB::table('auth_otp_challenges')
                ->where('user_id', $user->id)
                ->delete();

            return $this->otpError(
                'Unable to send verification email. Please try again later.',
                503
            );
        }

        $localPart = explode('@', $user->email)[0];
        $domain = explode('@', $user->email)[1];

        $maskedEmail = substr($localPart, 0, 1)
            . '***@' . $domain;

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent. Please check your email.',
            'data' => [
                'verification_required' => true,
                'challenge_id' => $id,
                'email_masked' => $maskedEmail,
                'expires_in' => 300,
            ],
        ], $status);
    }

    private function otpHash(
        string $otp,
        User $user
    ): string {
        // Bind each OTP to the current email and password hash.
        // Email/password changes invalidate an outstanding code.
        $input = $otp . '|'
            . strtolower($user->email) . '|'
            . $user->password;

        return hash_hmac(
            'sha256',
            $input,
            (string) config('app.key')
        );
    }

    private function otpError(
        string $message,
        int $status
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
