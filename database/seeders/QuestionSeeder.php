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
        $users = User::all();
        $subjects = Subject::all();

        foreach (range(1, 100) as $index) {
            $question = Question::create([
                'question' => 'Sample question ' . $index,
                'image' => $index % 2 === 0 ? 'sample-image-' . $index . '.jpg' : null,
                'vote' => rand(0, 100),
                'subject_id' => $subjects->random()->id,
                'group_question_id' => null,
                'user_id' => $users->random()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
