<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Comment;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ambil semua data yang diperlukan
        $users = User::all();
        $questions = Question::all();
        $answers = Answer::all();
        $comments = Comment::all();

        // Gabungkan semua item yang bisa dilaporkan (reportable) ke dalam satu collection
        $reportables = $questions->toBase()->merge($answers)->merge($comments);

        if ($users->isEmpty() || $reportables->isEmpty()) {
            $this->command->error('Pastikan ada data di tabel users, questions, answers, dan comments sebelum menjalankan seeder ini.');
            return;
        }

        $this->command->info('Membuat dummy reports...');
        
        // 2. Buat loop untuk generate laporan
        foreach (range(1, 350) as $index) {
            $reportableItem = $reportables->random();
            $reporter = $users->random();

            if ($reporter->id === $reportableItem->user_id) {
                continue;
            }
            
            $reportData = [
                'reason' => fake()->randomElement(['Spam', 'Konten Tidak Pantas', 'Informasi Palsu', 'Pelanggaran Hak Cipta', 'Ujaran Kebencian']),
                'preview' => "Teks konten yang dilaporkan: '" . substr($reportableItem->question ?? $reportableItem->answer ?? $reportableItem->comment, 0, 100) . "...'",
                'additional_notes' => rand(0, 1) ? fake()->sentence() : null,
                'status' => fake()->randomElement(['pending', 'reviewed', 'resolved']),
            ];

            $reportableItem->report($reporter->id, $reportData);
        }

        $this->command->info('Dummy reports berhasil dibuat.');
    }
}