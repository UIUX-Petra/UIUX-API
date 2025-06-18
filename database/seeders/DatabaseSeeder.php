<?php

namespace Database\Seeders;

use App\Models\User;
use Egulias\EmailValidator\Result\Reason\Reason;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);
        $this->call(FollowSeeder::class);
        $this->call(SubjectSeeder::class);
        $this->call(QuestionSeeder::class);
        $this->call(AnswerSeeder::class);
        $this->call(CommentSeeder::class);
        $this->call(GroupQuestionSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(ReportReasonSeeder::class);
        $this->call(ReportSeeder::class);
        $this->call(AnnouncementSeeder::class);
    }
}
