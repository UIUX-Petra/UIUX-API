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
        $subjectIds = Subject::pluck('id')->toArray();

        foreach (range(1, 100) as $index) {
            Question::create([
                'title' => 'title ' .$index,
                'question' => 'Sample question ' . $index,
                'image' => $index === 2 ? 'sample-image-' . $index . '.jpg' : null,
                'vote' => rand(0, 100),
                'subject_id' => $subjectIds[array_rand($subjectIds)],
                'group_question_id' => null,
                'user_id' => $userIds[array_rand($userIds)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
