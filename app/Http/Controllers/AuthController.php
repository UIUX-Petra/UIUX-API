<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailVerification; // assuming VerificationEmail is your Mailable class

use App\Models\User;
use App\Utils\HttpResponseCode;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseController
{
    protected $userController;

    public function __construct()
    {
        $this->userController = new UserController(new User());
    }

    public function manualLogin(Request $request)
    {
        $user = $this->userController->model::where("email", $request->usernameOrEmail)->orWhere("username", $request->usernameOrEmail)->first();
        if (!$user) {
            return $this->error("User or Email Not Found", HttpResponseCode::HTTP_NOT_FOUND);
        }
        if (!Hash::check($request->loginPassword, $user->password)) {
            return $this->error("Wrong Password", HttpResponseCode::HTTP_UNAUTHORIZED);
        }

        if (!$user->hasVerifiedEmail()) {
            return $this->error('Please verify your email first.',HttpResponseCode::HTTP_UNAUTHORIZED); // Forbidden
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
            ]
        );
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();
        $validated['password'] = Hash::make($validated['password']);

        // Create a unique token for this registration
        $token = Str::random(60);

        // Store the user data temporarily in Redis or Session
        Cache::put("pending_user_{$token}", $validated, now()->addMinutes(30));  // Expire after 30 minutes

        // Send email with the verification link containing the token
        $user = $validated;
        $user['token'] = $token;
        Mail::to($validated['email'])->send(new EmailVerification($user));

        return $this->success('User registered successfully. Please check your email for verification.', HttpResponseCode::HTTP_CREATED);
    }

    public function verifyEmail($token)
    {
        // Retrieve the user data from the cache using the token
        $userData = Cache::get("pending_user_{$token}");

        if (!$userData) {
            return response()->json(['message' => 'Verification link is invalid or expired.'], 400);
        }

        // The user data is valid, so now store it in the main `users` table
        $user = User::create([
            'username' => $userData['username'],
            'email' => $userData['email'],
            'password' => $userData['password'],
            'verified' => true, // Mark as verified
        ]);

        // Mark the email as verified
        $user->markEmailAsVerified();

        // Delete the temporary user data from the cache
        Cache::forget("pending_user_{$token}");

        return response()->json(['message' => 'Email verified successfully. You can now log in.'], 200);
    }






    public function login(Request $request)
    {
        $creds = $request->only(['password']);
        if (env('API_SECRET') != $creds['password']) {
            return $this->error('Invalid API Password!', HttpResponseCode::HTTP_UNAUTHORIZED);
        }

        $data = $request->only(['name', 'email']);
        $user = $this->userController->firstOrCreate($data);

        if (!$user) {
            return $this->error('User not found', HttpResponseCode::HTTP_NOT_FOUND);
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
            ]
        );
    }
}
