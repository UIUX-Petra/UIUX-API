<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Admin;
use App\Utils\HttpResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends BaseController
{
    public function __construct(Admin $admin)
    {
        parent::__construct($admin);
    }
    public function socialiteLogin(Request $request)
    {
        Log::info('Menerima permintaan socialite login baru.', $request->all());

        if (!env('API_SECRET') || $request->input('secret') !== env('API_SECRET')) {
            return $this->error('Please Regist From ' . env('APP_NAME') . ' Website!', HttpResponseCode::HTTP_UNAUTHORIZED);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
        ]);

        if ($validator->fails()) {
            Log::warning('Validasi socialite login gagal.', ['errors' => $validator->errors()]);
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();
        $email = $validated['email'];

        $admin = Admin::with('roles')->where('email', $email)->first();

        if (!$admin) {
            Log::warning('Percobaan login gagal: Email tidak terdaftar sebagai admin.', ['email' => $email]);
            return $this->error(
                'Your email is not registered as an administrator. Please contact support.',
                HttpResponseCode::HTTP_FORBIDDEN
            );
        }

        Log::info('Admin ditemukan.', ['id' => $admin->id, 'email' => $admin->email]);
        Log::info('Email admin telah diverifikasi.', ['id' => $admin->id]);
        $admin->tokens()->delete();

        $abilities = $admin->roles->pluck('slug')->all();
        if (empty($abilities)) {
            Log::warning('Admin login denied: No roles assigned.', ['id' => $admin->id]);
            return $this->error(
                'Your admin account does not have any roles assigned. Please contact support.',
                HttpResponseCode::HTTP_FORBIDDEN
            );
        }

        $adminToken = $admin->createToken('Admin_token', $abilities)->plainTextToken;
        Log::info('Token baru berhasil dibuat untuk admin.', ['id' => $admin->id]);

        $responseData = [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'token' => $adminToken,
            'roles' => $abilities,
        ];

        return $this->success(
            'Login successful!',
            $responseData
        );
    }
}
