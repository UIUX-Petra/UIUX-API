<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userIds = User::pluck('id')->toArray();

        foreach (range(1, 100) as $index) {
            Question::create([
                'title' => 'Sample title' . $index,
                'question' => 'Sample question ' . $index,
                'image' => $index % 2 === 0 ? 'sample-image-' . $index . '.jpg' : null,
                'vote' => rand(0, 100),
                'view' => rand(0, 200),
                'user_id' => $userIds[array_rand($userIds)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
