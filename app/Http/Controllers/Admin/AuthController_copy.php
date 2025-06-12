<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController; // Gunakan BaseController Anda jika ada
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Utils\HttpResponseCode;


class AuthController extends BaseController
{
    /**
     * Menangani permintaan login untuk admin.
     */
    public function login(Request $request)
    {
        // 1. Validasi input
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // 2. Cari admin berdasarkan email di tabel 'admins'
        $admin = Admin::where('email', $request->email)->first();

        // 3. Verifikasi admin dan password
        if (!$admin) {
            return $this->error('You are not an admin', HttpResponseCode::HTTP_CONFLICT);
        }
        if (!Hash::check($request->password, $admin->password)) {
            return $this->error('Your entered the wrong password', HttpResponseCode::HTTP_CONFLICT);
        }

        // 1. Ambil semua 'slug' dari role yang dimiliki admin ini.
        //    Contoh hasilnya: ['super-admin', 'moderator']
        $abilities = $admin->roles()->pluck('slug')->toArray();

        // Jika admin tidak punya role sama sekali, kita bisa beri ability default
        if (empty($abilities)) {
            $abilities = ['default']; // atau biarkan kosong: []
        }

        // Hapus token lama
        $admin->tokens()->delete();

        // 2. Buat token baru dengan SEMUA role slug sebagai abilities-nya.
        $token = $admin->createToken('admin-auth-token', $abilities)->plainTextToken;

        // ===================================
        // AKHIR PENYESUAIAN
        // ===================================

        return $this->success(
            'Admin login successful!',
            [
                'admin' => $admin->load('roles:name'), // Kirim juga data role di response
                'token' => $token,
            ]
        );
    }

    /**
     * Menangani permintaan logout untuk admin yang sedang login.
     */
    public function logout(Request $request)
    {
        // Hapus token yang sedang digunakan untuk request ini
        $request->user()->currentAccessToken()->delete();

        return $this->success('Admin logged out successfully.');
    }

    /**
     * Mengambil data profil admin yang sedang login.
     */
    public function me(Request $request)
    {
        // $request->user() akan berisi instance model Admin yang terotentikasi
        return $this->success('Admin profile fetched successfully', $request->user());
    }
}
