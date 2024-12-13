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
        $users = User::all();
        $questions = Question::all();

        foreach (range(1, 200) as $index) {
            Answer::create([
                'vote' => rand(0, 50),
                'image' => $index % 3 === 0 ? 'answer-image-' . $index . '.png' : null,
                'answer' => 'Sample answer text for answer ' . $index,
                'question_id' => $questions->random()->id,
                'user_id' => $users->random()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
