<?php

namespace Database\Seeders;

use App\Models\GroupQuestion;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GroupQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = Subject::pluck('id')->toArray();
        $questions = Question::pluck('id')->toArray();

        foreach (range(1, 50) as $index) {
            GroupQuestion::create([
                'tag_id' => $tags[array_rand($tags)],
                'question_id' => $questions[array_rand($questions)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
