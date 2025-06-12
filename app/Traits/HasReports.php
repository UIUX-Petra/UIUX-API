<?php

namespace App\Traits;

use App\Models\Report;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasReports
{
    /**
     * Mendefinisikan relasi polimorfik ke model Report.
     * Model ini bisa memiliki banyak laporan.
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * Membuat laporan baru untuk model ini.
     *
     * @param int|string $userId ID dari pengguna yang melaporkan.
     * @param array $data Data laporan (misal: reason, preview, notes).
     * @return \App\Models\Report Laporan yang baru dibuat.
     */
    public function report($userId, array $data): Report
    {
        // Gabungkan data laporan dengan user_id
        $reportData = array_merge($data, ['user_id' => $userId]);

        // Buat record baru di tabel reports melalui relasi
        $report = $this->reports()->create($reportData);

        // Tambahkan counter di model utama (misal: di tabel questions atau answers)
        // Pastikan model Anda punya kolom 'reports_count'
        if (method_exists($this, 'increment')) {
             $this->increment('report');
        }
        
        return $report;
    }

    /**
     * Memeriksa apakah model ini pernah dilaporkan oleh user tertentu.
     *
     * @param int|string $userId
     * @return bool
     */
    public function hasBeenReportedByUser($userId): bool
    {
        return $this->reports()->where('user_id', $userId)->exists();
    }
}