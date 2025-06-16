<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role; 

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding application roles...');

        $roles = [
            [
                'name' => 'Super Administrator',
                'slug' => 'super-admin',
                'description' => 'Full access to all platform features, including user management, role management, content moderation, and system settings.',
            ],
            [
                'name' => 'Content Manager',
                'slug' => 'content-manager',
                'description' => 'Can perform CRUD operations on Subjects. Can directly edit or delete any Question, Answer, or Comment.',
            ],
            [
                'name' => 'Moderator',
                'slug' => 'moderator',
                'description' => 'Focuses on the Moderation Reports page. Can approve (delete content) or reject reports. Cannot freely edit content or manage users.',
            ],
            [
                'name' => 'User Manager',
                'slug' => 'user-manager',
                'description' => 'Can view the user list and their contributions. Can block or suspend user accounts.',
            ],
            [
                'name' => 'Community Manager',
                'slug' => 'community-manager',
                'description' => 'Responsible for creating and managing platform-wide Announcements for all users.',
            ],
            [
                'name' => 'Analyst',
                'slug' => 'analyst',
                'description' => 'Read-only access to the Statistics & Reporting dashboards to analyze platform data.',
            ],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['slug' => $roleData['slug']], 
                [
                    'name' => $roleData['name'],
                    'description' => $roleData['description']
                ]
            );
        }

        $this->command->info('Application roles seeded successfully.');
    }
}
