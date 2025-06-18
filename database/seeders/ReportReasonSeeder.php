<?php

namespace Database\Seeders;

use App\Models\ReportReason;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReportReasonSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Membuat atau memastikan data Report Reasons ada...');

        $reasons = [
            ['title' => 'Spam', 'description' => 'Konten promosi atau berulang yang tidak relevan.'],
            ['title' => 'Konten Tidak Pantas', 'description' => 'Mengandung unsur pornografi, kekerasan, atau hal yang menyinggung.'],
            ['title' => 'Informasi Palsu', 'description' => 'Menyebarkan berita bohong atau misinformasi.'],
            ['title' => 'Pelanggaran Hak Cipta', 'description' => 'Menggunakan konten milik orang lain tanpa izin.'],
            ['title' => 'Ujaran Kebencian', 'description' => 'Menyerang individu atau kelompok berdasarkan ras, agama, dll.'],
            ['title' => 'Lainnya', 'description' => 'Alasan lain yang tidak tercakup dalam kategori di atas.'],
        ];

        foreach ($reasons as $reason) {
            ReportReason::firstOrCreate(['title' => $reason['title']], $reason);
        }

        $this->command->info('Data Report Reasons berhasil di-seed.');
    }
}