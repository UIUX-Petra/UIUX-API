<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\Question;
use App\Models\Answer;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
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
                'user:id,username,image',
                'reason:id,title', 
                'reviewer:id,name',
                'reportable' => function ($morphTo) {
                    $morphTo->morphWith([
                        \App\Models\Question::class => ['user:id,username'],
                        \App\Models\Answer::class => ['user:id,username', 'question:id,title'],
                        \App\Models\Comment::class => ['user:id,username'],
                    ]);
                },
            ]);
            if ($request->filled('reason_id')) {
                $reportsQuery->where('report_reason_id', $request->query('reason_id'));
            }
            // Filter berdasarkan tipe
            if ($request->filled('type')) {
                $reportsQuery->where('reportable_type', $request->query('type'));
            }
            $status = $request->query('status', 'pending');
            $reportsQuery->where('status', $status);
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
                    $query->where('preview', 'like', "%{$search}%")
                        ->orWhere('additional_notes', 'like', "%{$search}%")
                        // Cari berdasarkan nama pelapor
                        ->orWhereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%"))
                        // Cari berdasarkan judul alasan
                        ->orWhereHas('reason', fn($q) => $q->where('title', 'like', "%{$search}%"))
                        // Cari di konten yang dilaporkan
                        ->orWhereHasMorph('reportable', '*', function ($subQuery) use ($search) {
                            $subQuery->where('content', 'like', "%{$search}%")
                                ->orWhere('title', 'like', "%{$search}%"); // Untuk Question
                        });
                });
            }

            $perPage = $request->query('per_page', 5);
            $reports = $reportsQuery->latest()->paginate($perPage);

            return ReportResource::collection($reports);
        } catch (\Exception $e) {
            // Log::error('An exception occurred while fetching reports.', [
            //     'message' => $e->getMessage(),
            //     'file' => $e->getFile(),
            //     'line' => $e->getLine(),
            // ]);

            return response()->json(['success' => false, 'message' => 'An internal server error occurred.'], 500);
        }
    }

    public function processReport(Request $request, Report $report)
{
    $request->validate([
        'action' => 'required|string|in:approve,reject',
    ]);

    if ($report->status !== 'pending') {
        return response()->json([
            'success' => false,
            'message' => 'This report has already been processed.',
        ], 409);
    }

    $action = $request->input('action');
    $reportable = $report->reportable;
    $adminId = Auth::id();

    // Log::info('Processing report.', [
    //     'report_id' => $report->id,
    //     'action' => $action,
    //     'admin_id' => $adminId,
    // ]);

    try {
        $newStatus = ($action === 'approve') ? 'resolved' : 'rejected';

        $report->update([
            'status' => $newStatus,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
        ]);
        Log::info("Report {$report->id} status updated to {$newStatus}.");


        if ($action === 'approve') {
            if ($reportable) {
                $reportable->delete();
                // Log::info('Report approved. Content deleted.', ['report_id' => $report->id]);
            }
        } else { // action === 'reject'
            // Log::info('Report rejected. Content remains.', ['report_id' => $report->id]);
        }

        return response()->json([
            'success' => true,
            'message' => "Report has been successfully marked as {$newStatus}.",
        ], 200);

    } catch (\Exception $e) {
        // Log::error('Failed to process report.', [
        //     'report_id' => $report->id,
        //     'error' => $e->getMessage(),
        //     'trace' => $e->getTraceAsString() 
        // ]);
        return response()->json(['success' => false, 'message' => 'Failed to process report.'], 500);
    }
}
    public function getContentDetail(string $type, string $id)
    {
        // Log::info("START: Fetching content detail.", ['type' => $type, 'id' => $id]);

        try {
            $data = null;
            $safeType = strtolower(basename($type));
            // Log::debug("Sanitized type to '{$safeType}'.");

            switch ($safeType) {
                case 'question':
                    // Log::debug("Executing 'question' case.");
                    $data = Question::with([
                        'user',
                        'answer' => function ($query) {
                            // Log::debug("Building 'answer' subquery for Question.");
                            $query->with(['user', 'comment.user'])
                                ->withCount(['reports'])
                                ->orderBy('verified', 'desc')
                                ->orderBy('vote', 'desc')
                                ->limit(3);
                        }
                    ])
                        ->withCount(['comment'])
                        ->findOrFail($id);
                    // Log::info("SUCCESS: Found Question.", ['id' => $id, 'answers_loaded' => $data->answer->count()]);
                    break;

                case 'answer':
                    // Log::debug("Executing 'answer' case.");
                    $data = Answer::with(['user', 'question:id,title', 'comment.user'])
                        ->withCount(['comment'])
                        ->findOrFail($id);
                    // Log::info("SUCCESS: Found Answer.", ['id' => $id, 'comments_loaded' => $data->comment->count()]);
                    break;

                case 'comment':
                    // Log::debug("Executing 'comment' case.");
                    $data = Comment::with(['user', 'commentable'])
                        ->findOrFail($id);
                    // Log::info("SUCCESS: Found Comment. Preparing to send data.", ['id' => $id, 'data' => $data->toArray()]);
                    break;

                default:
                    // Log::warning("FAILED: Invalid content type provided in getContentDetail.", ['type' => $type]);
                    return response()->json(['message' => 'Invalid content type provided.'], 400);
            }

            $data->type = $safeType;

            // Log::info("END: Successfully fetched and prepared content detail.", ['type' => $safeType, 'id' => $id]);
            return response()->json($data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Log::error("EXCEPTION: Content not found in getContentDetail.", [
            //     'type' => $type, 
            //     'id' => $id,
            //     'exception' => $e->getMessage()
            // ]);
            return response()->json(['message' => 'Content not found.'], 404);
        } catch (\Exception $e) {
            // Log::error("EXCEPTION: A critical error occurred while fetching content detail.", [
            //     'type' => $type, 
            //     'id' => $id,
            //     'error' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString() 
            // ]);
            return response()->json(['message' => 'An internal server error occurred.'], 500);
        }
    }
}
