<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [];

        for ($i = 1; $i <= 200; $i++) {
            $users[] = [
                'id' => Str::uuid()->toString(),
                'username' => "User_$i",
                'email' => "user$i@example.com",
                'biodata' => null,
                'password' => \Illuminate\Support\Facades\Hash::make(12345678),
                'reputation' => rand(0, 100),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        User::insert($users);
    }
}
