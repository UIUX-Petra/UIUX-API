<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController; // Gunakan BaseController Anda jika ada
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Utils\HttpResponseCode;


class AuthController_Copy extends BaseController
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin) {
            return $this->error('You are not an admin', HttpResponseCode::HTTP_CONFLICT);
        }
        if (!Hash::check($request->password, $admin->password)) {
            return $this->error('Your entered the wrong password', HttpResponseCode::HTTP_CONFLICT);
        }


        $abilities = $admin->roles()->pluck('slug')->toArray();

        if (empty($abilities)) {
            $abilities = ['default'];
        }

        $admin->tokens()->delete();

        $token = $admin->createToken('admin-auth-token', $abilities)->plainTextToken;


        return $this->success(
            'Admin login successful!',
            [
                'admin' => $admin->load('roles:name'),
                'token' => $token,
            ]
        );
    }


    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success('Admin logged out successfully.');
    }


    public function me(Request $request)
    {
        return $this->success('Admin profile fetched successfully', $request->user());
    }
}
