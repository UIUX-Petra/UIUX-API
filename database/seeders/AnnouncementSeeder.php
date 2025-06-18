<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;
use App\Models\Announcement;
use Carbon\Carbon;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Utama',
                'password' => Hash::make('password'),
            ]
        );

        $announcements = [
            [
                'title' => 'Maintenance Sistem Terjadwal',
                'detail' => 'Akan diadakan maintenance sistem pada hari Sabtu ini pukul 22:00 WIB hingga Minggu pukul 02:00 WIB. Mohon untuk tidak mengakses sistem pada waktu tersebut.',
                'status' => 'published',
                'display_on_web' => true,
                'published_at' => Carbon::now()->subDays(2),
                'notified_at' => Carbon::now()->subDays(2)->addMinutes(5),
            ],
            [
                'title' => 'Update Kebijakan Privasi Terbaru',
                'detail' => 'Kami telah memperbarui kebijakan privasi kami untuk meningkatkan perlindungan data Anda. Silakan tinjau perubahan tersebut di halaman kebijakan privasi kami.',
                'status' => 'published',
                'display_on_web' => true,
                'published_at' => Carbon::now()->subDays(5),
                'notified_at' => null, 
            ],
            [
                'title' => 'Libur Nasional Hari Raya Idul Adha',
                'detail' => 'Sehubungan dengan Hari Raya Idul Adha, seluruh kegiatan operasional akan diliburkan pada tanggal yang telah ditetapkan pemerintah. Kegiatan akan kembali normal pada hari kerja berikutnya.',
                'status' => 'published',
                'display_on_web' => true,
                'published_at' => Carbon::now()->subWeek(),
                'notified_at' => Carbon::now()->subWeek(),
            ],
            [
                'title' => 'Draft Pengumuman Rapat Bulanan',
                'detail' => 'Detail rapat bulanan untuk bulan Juli akan segera diumumkan. Mohon ditunggu.',
                'status' => 'draft', 
                'display_on_web' => false,
                'published_at' => null,
                'notified_at' => null,
            ],
            [
                'title' => 'Pengumuman Pemenang Lomba 17 Agustus (Diarsipkan)',
                'detail' => 'Berikut adalah daftar pemenang lomba dalam rangka perayaan HUT RI ke-79. Selamat kepada para pemenang!',
                'status' => 'archived', 
                'display_on_web' => false, 
                'published_at' => Carbon::now()->subMonths(2),
                'notified_at' => Carbon::now()->subMonths(2),
            ]
        ];

        foreach ($announcements as $announcementData) {
            Announcement::updateOrCreate(
                ['title' => $announcementData['title']],
                array_merge($announcementData, ['admin_id' => $admin->id])
            );
        }
    }
}
