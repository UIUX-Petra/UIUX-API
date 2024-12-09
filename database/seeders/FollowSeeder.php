<?php

namespace Database\Seeders;

use App\Models\User;
use DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Str;

class FollowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all(); // Fetch all users

        $follows = [];

        foreach ($users as $follower) {
            // Each user will follow up to 10 random users
            $followedUsers = $users->where('id', '!=', $follower->id)->random(rand(1, 10));

            foreach ($followedUsers as $followed) {
                $follows[] = [
                    'id' => Str::uuid()->toString(),
                    'follower_id' => $follower->id,
                    'followed_id' => $followed->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Insert follows into the database
        DB::table('follows')->insert($follows);
    }
}
