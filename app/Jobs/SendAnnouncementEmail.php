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

        public function __construct(protected Announcement $announcement)
        {
        }

        public function handle(): void
        {
            // Ambil semua pengguna aktif (asumsi ada kolom 'is_active' atau sejenisnya)
            $activeUsers = User::where('is_active', true)->get();

            foreach ($activeUsers as $user) {
                Mail::to($user->email)->queue(new AnnouncementPublished($this->announcement));
            }

            // Tandai kapan notifikasi selesai dikirim
            $this->announcement->update(['notified_at' => now()]);
        }
    }
    