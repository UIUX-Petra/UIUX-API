<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Comment;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Str;

class CommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $questions = Question::all();
        $answers = Answer::all();

        if ($users->isEmpty() || ($questions->isEmpty() && $answers->isEmpty())) {
            $this->command->error('Ensure you have users, questions, and answers in your database before running this seeder.');
            return;
        }

        for ($i = 0; $i < 500; $i++) {
            $user = $users->random();

            $isForQuestion = random_int(0, 1);
            $questionId = $isForQuestion ? $questions->random()->id : null;
            $answerId = !$isForQuestion ? $answers->random()->id : null;

            Comment::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'question_id' => $questionId,
                'answer_id' => $answerId,
                'comment' => fake()->sentence(), // Using Laravel's fake data generator
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
