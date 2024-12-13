<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AnswerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userIds = User::pluck('id')->toArray();
        $questionIds = Question::pluck('id')->toArray();

        foreach (range(1, 200) as $index) {
            Answer::create([
                'vote' => rand(0, 50),
                'image' => $index % 3 === 0 ? 'answer-image-' . $index . '.png' : null,
                'answer' => 'Sample answer text for answer ' . $index,
                'question_id' => $questionIds[array_rand($questionIds)],
                'user_id' => $userIds[array_rand($userIds)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
