<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\User; // Asumsi model User Anda ada di sini
use App\Mail\AnnouncementPublished;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAnnouncementEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Announcement $announcement;
    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    public function handle(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            Mail::to($user->email)->queue(new AnnouncementPublished($this->announcement));
        }

        $this->announcement->update(['notified_at' => now()]);
    }
}
