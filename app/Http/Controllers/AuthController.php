<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Utils\HttpResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends BaseController
{
    protected $userController;

    public function __construct()
    {
        $this->userController = new UserController(new User());
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
