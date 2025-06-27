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
            ['title' => 'Spam', 'description' => 'Irrelevant promotional or repetitive content.'],
            ['title' => 'Inappropriate Content', 'description' => 'Contains pornography, violence, or offensive material.'],
            ['title' => 'False Information', 'description' => 'Spreading false news or misinformation.'],
            ['title' => 'Copyright Infringement', 'description' => 'Using someone else\'s content without permission.'],
            ['title' => 'Hate Speech', 'description' => 'Attacking an individual or group based on race, religion, etc.'],
            ['title' => 'Others', 'description' => 'Other reasons not covered in the categories above.'],
        ];

        foreach ($reasons as $reason) {
            ReportReason::firstOrCreate(['title' => $reason['title']], $reason);
        }

        $this->command->info('Data Report Reasons berhasil di-seed.');
    }
}
