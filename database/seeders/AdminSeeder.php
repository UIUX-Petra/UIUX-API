<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;   // <-- TAMBAHKAN INI
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;           // <-- TAMBAHKAN INI

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Membuat Roles...');

        // 1. Buat Roles (Tidak ada perubahan di sini)
        $superAdminRole = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin']
        );

        $moderatorRole = Role::firstOrCreate(
            ['slug' => 'moderator'],
            ['name' => 'Moderator']
        );

        $this->command->info('Roles berhasil dibuat.');
        $this->command->info('Membuat Admins...');

        // 2. Buat Akun Admin (Tidak ada perubahan di sini)
        $superAdmin = Admin::firstOrCreate(
            ['email' => 'c14230250@john.petra.ac.id'],
            [
                'name' => 'Jessica Chandra',
                'password' => Hash::make('password'),
            ]
        );
        
        $moderatorAdmin = Admin::firstOrCreate(
            ['email' => 'moderator@example.com'],
            [
                'name' => 'Content Moderator',
                'password' => Hash::make('password'),
            ]
        );

        $this->command->info('Admins berhasil dibuat.');
        $this->command->info('Menghubungkan Roles ke Admins...');

     
        if (!DB::table('admin_role')->where('admin_id', $superAdmin->id)->where('role_id', $superAdminRole->id)->exists()) {
            DB::table('admin_role')->insert([
                'id' => Str::uuid(), // Buat UUID baru secara manual
                'admin_id' => $superAdmin->id,
                'role_id' => $superAdminRole->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Hubungkan Moderator dengan Role Moderator
        if (!DB::table('admin_role')->where('admin_id', $moderatorAdmin->id)->where('role_id', $moderatorRole->id)->exists()) {
            DB::table('admin_role')->insert([
                'id' => Str::uuid(), // Buat UUID baru secara manual
                'admin_id' => $moderatorAdmin->id,
                'role_id' => $moderatorRole->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }


        $this->command->info('Proses seeding admin dan role selesai.');
    }
}