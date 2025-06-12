<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource; // Ini akan kita buat di langkah 3
use App\Models\Answer;
use App\Models\Comment;
use App\Models\Question;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        Log::info('Memproses permintaan untuk daftar laporan.', [
            'type' => $request->query('type'),
            'search' => $request->query('search'),
            'per_page' => $request->query('per_page', 10),
            'ip_address' => $request->ip(),
        ]);

        $request->validate([
            'type' => 'nullable|string|in:question,answer,comment',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $reportsQuery = Report::with(['user', 'reportable']);

        if ($request->filled('type')) {
            $type = $request->query('type');

            Log::debug('Menerapkan filter tipe laporan.', ['type' => $type]);

            $reportableClass = match ($type) {
                'question' => Question::class,
                'answer'   => Answer::class,
                'comment'  => Comment::class,
                default    => null,
            };
            Log::debug('Menerapkan filter tipe laporan.', ['reportableClass' => $reportableClass]);


            if ($reportableClass) {
                $reportsQuery->where('reportable_type', $reportableClass);
                Log::debug('reportsQuery.', ['reportQuery' => $reportsQuery]);
            }
        }

        if ($request->filled('search')) {
            $search = $request->query('search');

            Log::debug('Menerapkan filter pencarian.', ['keyword' => $search]);

            $reportsQuery->where(function ($query) use ($search) {
                $query->where('reason', 'like', "%{$search}%")
                    ->orWhere('preview', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($subQuery) use ($search) {
                        $subQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->query('per_page', 10);
        $reports = $reportsQuery->latest()->paginate($perPage);

        Log::info('Berhasil mengambil data laporan dari database.', [
            'items_fetched' => $reports->count(),
            'total_items' => $reports->total(),
            'current_page' => $reports->currentPage(),
        ]);

        return ReportResource::collection($reports);
    }
}
