<?php

namespace App\Http\Controllers;

use App\Mail\VerifyPendingRegistrationMail;
use App\Models\PendingUser;
use App\Models\User;
use App\Utils\HttpResponseCode;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Str;

class AuthController extends BaseController
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }
    public function register(Request $request)
    {
        if (!env('API_SECRET') || $request->input('secret') !== env('API_SECRET')) {
            Log::info(env('API_SECRET'));
            Log::info($request->input('secret'));

            return $this->error('Please Regist From ' . env('APP_NAME') . ' Website!', HttpResponseCode::HTTP_UNAUTHORIZED);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }
        $validated = $validator->validated();
        $inputEmail = $validated['email'];
        $inputUsername = $validated['username'];
        $inputPassword = $validated['password'];

        $existingUser = User::where('email', $inputEmail)->first();

        if ($existingUser) {
            if (!is_null($existingUser->password)) {
                return $this->error('Email already registered with a password. Please login or use a different email.', HttpResponseCode::HTTP_CONFLICT);
            }

            if (strtolower($existingUser->username) !== strtolower($inputUsername) && User::where('username', $inputUsername)->where('id', '!=', $existingUser->id)->exists()) {
                return $this->error('This username is already taken by another account. Please check and try a different username.', HttpResponseCode::HTTP_CONFLICT);
            }

            $pendingRecord = PendingUser::where(function ($query) use ($existingUser, $inputEmail) {
                $query->where('existing_user_id', $existingUser->id)
                    ->orWhere(function ($subQuery) use ($inputEmail) {
                        $subQuery->where('email', $inputEmail)->whereNull('existing_user_id');
                    });
            })
                ->orderBy('created_at', 'desc')
                ->first();

            if ($pendingRecord) {
                if (now()->lt($pendingRecord->expires_at)) {
                    try {
                        $pendingRecord->expires_at = now()->addHours(config('auth.verification.pending_expire', 24));

                        if (is_null($pendingRecord->existing_user_id)) {
                            $pendingRecord->existing_user_id = $existingUser->id;
                        }
                        $pendingRecord->username = $inputUsername;
                        $pendingRecord->password = Hash::make($inputPassword);

                        $pendingRecord->save();

                        Mail::to($pendingRecord->email)->send(new VerifyPendingRegistrationMail($pendingRecord, true));
                        Log::info('Re-sent set password verification email for existing pending record: ' . $pendingRecord->email);
                        return $this->success('A password setup for this email was already pending. We have re-sent the verification email. Please check your inbox (and spam folder).', null, HttpResponseCode::HTTP_OK); // Use HTTP_OK as it's an update to an existing process

                    } catch (\Exception $e) {
                        Log::error('Failed to re-send set password verification email: ' . $e->getMessage() . ' for user: ' . $pendingRecord->email);
                        return $this->error('Password setup was pending, but there was an issue re-sending the verification email. Please contact support.', HttpResponseCode::HTTP_INTERNAL_SERVER_ERROR);
                    }
                } else {
                    $pendingRecord->delete();
                }
            }

            $token = Str::random(60);
            $newPendingUser = PendingUser::create([
                'username' => $inputUsername,
                'email' => $inputEmail,
                'password' => Hash::make($inputPassword),
                'existing_user_id' => $existingUser->id,
                'verification_token' => $token,
                'expires_at' => now()->addHours(config('auth.verification.pending_expire', 24)),
            ]);

            try {
                Mail::to($newPendingUser->email)->send(new VerifyPendingRegistrationMail($newPendingUser, true));
            } catch (\Exception $e) {
                Log::error('Failed to send set password verification email: ' . $e->getMessage() . ' for user: ' . $newPendingUser->email);
                $newPendingUser->delete();
                return $this->error('Password setup initiated, but there was an issue sending the verification email. Please contact support.', HttpResponseCode::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->success('Account exists via social login. Please check your email to verify and set your password.', null, HttpResponseCode::HTTP_CREATED);
        } else {
            if (User::where('username', $inputUsername)->exists()) {
                return $this->error('This username is already taken by a verified account. Please choose a different username.', HttpResponseCode::HTTP_CONFLICT);
            }

            $pendingRecord = PendingUser::where('email', $inputEmail)
                ->orWhere('username', $inputUsername)
                ->orderByRaw("CASE WHEN email = ? THEN 0 ELSE 1 END", [$inputEmail])
                ->orderBy('created_at', 'desc')
                ->first();

            if ($pendingRecord) {
                if (now()->lt($pendingRecord->expires_at)) {

                    try {
                        $pendingRecord->username = $inputUsername;
                        $pendingRecord->email = $inputEmail;
                        $pendingRecord->password = Hash::make($inputPassword);
                        $pendingRecord->expires_at = now()->addHours(config('auth.verification.pending_expire', 24));
                        $pendingRecord->existing_user_id = null;
                        $pendingRecord->save();

                        Mail::to($pendingRecord->email)->send(new VerifyPendingRegistrationMail($pendingRecord, false)); // false for 'isSettingPassword'
                        Log::info('Re-sent new registration verification email for existing pending record: ' . $pendingRecord->email);
                        return $this->success('A registration was already pending for this email or username. We have updated it with your latest information and re-sent the verification email. Please check your inbox (and spam folder).', null, HttpResponseCode::HTTP_OK); // OK for update

                    } catch (\Exception $e) {
                        Log::error('Failed to re-send new registration verification email: ' . $e->getMessage() . ' for user: ' . $pendingRecord->email);
                        return $this->error('Registration was pending, but there was an issue re-sending the verification email. Please contact support.', HttpResponseCode::HTTP_INTERNAL_SERVER_ERROR);
                    }
                } else {
                    $pendingRecord->delete();
                }
            }

            $token = Str::random(60);
            $newPendingUser = PendingUser::create([
                'username' => $inputUsername,
                'email' => $inputEmail,
                'password' => Hash::make($inputPassword),
                'verification_token' => $token,
                'expires_at' => now()->addHours(config('auth.verification.pending_expire', 24)),
                'existing_user_id' => null,
            ]);

            try {
                Mail::to($newPendingUser->email)->send(new VerifyPendingRegistrationMail($newPendingUser, false));
            } catch (\Exception $e) {
                Log::error('Failed to send new registration verification email: ' . $e->getMessage() . ' for user: ' . $newPendingUser->email);
                $newPendingUser->delete();
                return $this->error('Registration submitted, but there was an issue sending the verification email. Please contact support.', HttpResponseCode::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->success('Registration successful. Please check your email to verify your account.', null, HttpResponseCode::HTTP_CREATED);
        }
    }

    public function verifyPendingEmail(Request $request, $token)
    {
        $pendingUser = PendingUser::where('verification_token', $token)->first();

        if (!$pendingUser) {
            return $this->error('Invalid verification token.', HttpResponseCode::HTTP_NOT_FOUND);
        }

        if (now()->gt($pendingUser->expires_at)) {
            $pendingUser->delete();
            return $this->error('Verification link has expired. Please try again.', HttpResponseCode::HTTP_GONE);
        }

        $user = null;
        $message = '';

        if ($pendingUser->existing_user_id) {
            $user = User::find($pendingUser->existing_user_id);
            if (!$user) {
                $pendingUser->delete();
                return $this->error('Associated user account not found. Please contact support.', HttpResponseCode::HTTP_NOT_FOUND);
            }

            $user->password = $pendingUser->password;
            $user->username = $pendingUser->username;
            if (is_null($user->email_verified_at)) {
                $user->email_verified_at = now();
            }
            $user->save();
            $message = 'Password set successfully! You can now login with your new password.';
        } else {
            if (User::where('email', $pendingUser->email)->exists() || User::where('username', $pendingUser->username)->exists()) {
                $pendingUser->delete();
                return $this->error('This email or username is already registered and verified. Please login.', HttpResponseCode::HTTP_CONFLICT);
            }

            $user = User::create([
                'username' => $pendingUser->username,
                'email' => $pendingUser->email,
                'password' => $pendingUser->password,
                'email_verified_at' => now(),
                'reputation' => 0,
            ]);
            $message = 'Email verified successfully! You are now registered.';
        }

        $pendingUser->delete();

        $user->tokens()->delete();
        $userToken = $user->createToken('user_token', ['user'])->plainTextToken;

        return $this->success(
            $message,
            [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'token' => $userToken,
                'reputation' => $user->reputation
            ]
        );
    }

    public function socialiteLogin(Request $request)
    {
        if (!env('API_SECRET') || $request->input('secret') !== env('API_SECRET')) {
            return $this->error('Please Login From ' . env('APP_NAME') . ' Website!', HttpResponseCode::HTTP_UNAUTHORIZED);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }
        $validated = $validator->validated();
        $email = $validated['email'];
        $googleName = $validated['name'];

        $user = User::where('email', $email)->first();

        if ($user) {
            if (is_null($user->email_verified_at)) {
                $user->email_verified_at = now();
            }
            $user->save();
        } else {
            $pendingUser = PendingUser::where('email', $email)->first();
            if ($pendingUser) {
                $user = User::create([
                    'username' => $pendingUser->username,
                    'email' => $pendingUser->email,
                    'password' => null,
                    'email_verified_at' => now(),
                    'reputation' => 0,
                ]);
                $pendingUser->delete();
            } else {
                $usernameToTry = Str::slug($googleName, '');
                if (User::where('username', $usernameToTry)->exists()) {
                    $emailPrefix = Str::before($email, '@');
                    $usernameToTry = Str::slug($emailPrefix, '');
                    if (User::where('username', $usernameToTry)->exists()) {
                        $usernameToTry = Str::slug($emailPrefix . Str::random(4), '');
                    }
                }
                if (User::where('username', $usernameToTry)->exists()) {
                    return $this->error("Could not create a unique username. Please try manual registration or contact support.", HttpResponseCode::HTTP_CONFLICT);
                }

                $user = User::create([
                    'username' => $usernameToTry,
                    'email' => $email,
                    'password' => null,
                    'email_verified_at' => now(),
                    'reputation' => 0,
                ]);
            }
        }

        $user->tokens()->delete();
        $userToken = $user->createToken('user_token', ['user'])->plainTextToken;

        return $this->success(
            'Login successful!',
            [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'token' => $userToken,
                'reputation' => $user->reputation
            ]
        );
    }


    public function manualLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'usernameOrEmail' => 'required|string',
            'loginPassword' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }
        $validated = $validator->validated();

        $user = User::where("email", $validated['usernameOrEmail'])
            ->orWhere("username", $validated['usernameOrEmail'])
            ->first();

        if (!$user) {
            return $this->error("User or Email Not Found", HttpResponseCode::HTTP_NOT_FOUND);
        }

        if (is_null($user->password)) {
            return $this->error("This account was registered using social login. Please use that method or set a password if you wish to login manually.", HttpResponseCode::HTTP_UNAUTHORIZED);
        }

        if (!Hash::check($validated['loginPassword'], $user->password)) {
            return $this->error("Wrong Password", HttpResponseCode::HTTP_UNAUTHORIZED);
        }

        if (is_null($user->email_verified_at)) {
            return $this->error("Your email address is not verified. Please check your email for a verification link.", HttpResponseCode::HTTP_FORBIDDEN);
        }

        $user->tokens()->delete();
        $userToken = $user->createToken('user_token', ['user'])->plainTextToken;
        return $this->success(
            'Login success!',
            [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'token' => $userToken,
                'reputation' => $user->reputation
            ]
        );
    }

    public function resendPendingVerification(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email']);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }
        $email = $request->input('email');

        $pendingUser = PendingUser::where('email', $email)->first();
        if ($pendingUser) {
            if (now()->gt($pendingUser->expires_at)) {
                $pendingUser->delete();
                return $this->error('Your previous registration attempt has expired. Please register again.', HttpResponseCode::HTTP_GONE);
            }
            $pendingUser->expires_at = now()->addHours(config('auth.verification.pending_expire', 24));
            $pendingUser->save();
            Mail::to($pendingUser->email)->send(new VerifyPendingRegistrationMail($pendingUser));
            return $this->success('A new verification link has been sent to your email address.');
        }

        $user = User::where('email', $email)->first();
        if ($user && $user->hasVerifiedEmail()) {
            return $this->error('This email is already registered and verified. Please login.', HttpResponseCode::HTTP_CONFLICT);
        }

        if ($user && !$user->hasVerifiedEmail() && $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail) {
            $user->sendEmailVerificationNotification();
            return $this->success('A verification link has been sent to your existing account.');
        }

        return $this->error('No pending registration found for this email, or the email is already verified. Please register if you haven\'t.', HttpResponseCode::HTTP_NOT_FOUND);
    }

    public function verifyEmail(User $user)
    {
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 200);
        }
        if (!URL::hasValidSignature(request())) {
            return response()->json(['message' => 'Verification link has expired or is invalid.'], 403);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email verified successfully! You can now login.'], 200);
    }

    // Your existing resend for Laravel's default
    public function resendVerificationEmail(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email']);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 200);
        }
        if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail) {
            $user->sendEmailVerificationNotification();
            return response()->json(['message' => 'A new verification link has been sent.']);
        }
        return response()->json(['message' => 'Verification cannot be resent for this account.'], 400);
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
            return $this->success('Logged out successfully.');
        }
        return $this->error('Unauthenticated.', HttpResponseCode::HTTP_UNAUTHORIZED);
    }
}
