<?php

namespace App\Http\Controllers;

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
        $user = $this->userController->model::where('email', $request->email)->first();
        if ($user) {
            return $this->error('Email already exist', HttpResponseCode::HTTP_BAD_REQUEST);
        }
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
        $user = $this->userController->create($validated);

        // Send email verification link
        $user->sendEmailVerificationNotification();

        Log::info($user);
        return $this->success('User registered successfully.', HttpResponseCode::HTTP_CREATED);
    }
    public function verifyEmail($id, $hash)
    {
        // Log the verification attempt
        Log::info("Email verification attempt for user ID: {$id}");
    
        $user = User::find($id);
    
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
    
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 200);
        }
    
        // Check if the URL signature is valid
        if (!URL::hasValidSignature(request())) {
            return response()->json(['message' => 'Verification link has expired or is invalid.'], 403);
        }
    
        if (!hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link.'], 400);
        }
    
        $user->markEmailAsVerified();
    
        return response()->json(['message' => 'Email verified successfully!'], 200);
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