<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Comment;
use App\Models\Question;
use App\Models\ReportReason;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        $reasonIds = ReportReason::pluck('id');

        if ($reasonIds->isEmpty()) {
            $this->command->error('Data alasan laporan (Report Reasons) tidak ditemukan. Silakan jalankan ReportReasonSeeder terlebih dahulu.');
            return;
        }

        $users = User::all();
        $questions = Question::all();
        $answers = Answer::all();
        $comments = Comment::all();
        $reportables = $questions->toBase()->merge($answers)->merge($comments);

        if ($users->isEmpty() || $reportables->isEmpty()) {
            $this->command->error('Pastikan ada data di tabel users, questions, answers, dan comments.');
            return;
        }

        $this->command->info('Membuat dummy reports...');
        
        foreach (range(1, 350) as $index) {
            $reportableItem = $reportables->random();
            $reporter = $users->random();

            if ($reporter->id === $reportableItem->user_id) {
                continue;
            }
            
            $reportData = [
                'report_reason_id' => $reasonIds->random(),
                'preview' => "Teks konten yang dilaporkan: '" . substr($reportableItem->content ?? $reportableItem->title, 0, 100) . "...'",
                'additional_notes' => rand(0, 1) ? fake()->sentence() : null,
                'status' => fake()->randomElement(['pending', 'resolved', 'rejected']),
            ];

            $reportableItem->report($reporter->id, $reportData);
        }

        $this->command->info('Dummy reports berhasil dibuat.');
    }
}