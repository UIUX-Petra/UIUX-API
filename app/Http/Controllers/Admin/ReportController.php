<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        try {
            $request->validate([
                'type' => 'nullable|string|in:question,answer,comment',
                'search' => 'nullable|string|max:100',
                'per_page' => 'nullable|integer|min:5|max:100',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $reportsQuery = Report::with([
                'user',
                'reportable' => function ($morphTo) {
                    $morphTo->morphWith([
                        \App\Models\Question::class => ['user'],
                        \App\Models\Answer::class => ['user', 'question'],
                        \App\Models\Comment::class => ['user', 'commentable'],
                    ]);
                },
            ]);

            // Filter berdasarkan tipe
            if ($request->filled('type')) {
                $reportsQuery->where('reportable_type', $request->query('type'));
            }

            // Filter berdasarkan rentang tanggal
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $startDate = Carbon::parse($request->query('start_date'))->startOfDay();
                $endDate = Carbon::parse($request->query('end_date'))->endOfDay();
                $reportsQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            // Terapkan filter pencarian
            if ($request->filled('search')) {
                $search = $request->query('search');
                $reportsQuery->where(function ($query) use ($search) {
                    $query->where('reason', 'like', "%{$search}%")
                          ->orWhere('preview', 'like', "%{$search}%") 
                          ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                          ->orWhereHasMorph('reportable', '*', function ($subQuery, $type) use ($search) {
                                $subQuery->where('content', 'like', "%{$search}%");
                                if ($type === \App\Models\Question::class) {
                                    $subQuery->orWhere('title', 'like', "%{$search}%");
                                }
                          });
                });
            }

            $perPage = $request->query('per_page', 5);
            $reports = $reportsQuery->latest()->paginate($perPage);

            return ReportResource::collection($reports);

        } catch (\Exception $e) {
            Log::error('An exception occurred while fetching reports.', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json(['success' => false, 'message' => 'An internal server error occurred.'], 500);
        }
    }

     public function processReport(Request $request, Report $report)
    {
        $request->validate([
            'action' => 'required|string|in:approve,reject',
        ]);

        $action = $request->input('action');
        $reportable = $report->reportable; 

        Log::info('Processing report.', [
            'report_id' => $report->id,
            'action' => $action,
            'reportable_type' => $report->reportable_type,
            'reportable_id' => $report->reportable_id,
        ]);

        try {
            if ($action === 'approve') {
                // Logika "Approve": Setujui laporan & hapus konten yang dilaporkan.
                if ($reportable) {
                    $reportable->delete();
                    Log::info('Report approved. Content deleted.', ['report_id' => $report->id]);
                } else {
                    Log::warning('Report approved, but content was already deleted.', ['report_id' => $report->id]);
                }
            }
            
            // "approve" dan "reject", laporannya dianggap selesai dan dihapus dari antrian.
            $report->delete();

            return response()->json([
                'success' => true,
                'message' => "Report has been successfully {$action}d.",
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to process report.', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to process report.'], 500);
        }
    }
}
