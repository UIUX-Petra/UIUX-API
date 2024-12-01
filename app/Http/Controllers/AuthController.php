<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Utils\HttpResponseCode;
use Hash;
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
        Log::info($user);
        return $this->success('User registered successfully.', HttpResponseCode::HTTP_CREATED);
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
