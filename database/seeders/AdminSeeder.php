<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Fetching roles to assign...');
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        $moderatorRole = Role::where('slug', 'moderator')->first();

        if (!$superAdminRole || !$moderatorRole) {
            $this->command->error('Default roles (super-admin, moderator) not found. Please run the RoleSeeder first by calling it from DatabaseSeeder.php');
            return;
        }

        $this->command->info('Creating default admin accounts...');

        $superAdmins = [
            [
                [
                    'email' => 'c14230250@john.petra.ac.id',
                ],
                [
                    'name' => 'Jessica Chandra',
                    'password' => Hash::make('password'),
                ]
            ],
            [
                [
                    'email' => 'c14230074@john.petra.ac.id',
                ],
                [
                    'name' => 'Terry Clement',
                    'password' => Hash::make('password'),
                ]
            ]
        ];

        foreach ($superAdmins as $superAdmin) {
            $super = Admin::firstOrCreate(
                $superAdmin[0],
                $superAdmin[1]
            );
            $super->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }

        $moderatorAdmin = Admin::firstOrCreate(
            ['email' => 'moderator@example.com'],
            [
                'name' => 'Content Moderator',
                'password' => Hash::make('password'),
            ]
        );
        // Tugaskan peran Moderator
        $moderatorAdmin->roles()->syncWithoutDetaching([$moderatorRole->id]);

        $this->command->info('Admin seeding process completed.');
    }
}
