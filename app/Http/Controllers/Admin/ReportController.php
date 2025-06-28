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
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        Log::info('ReportController@index: Request received.', $request->all());
        try {
            $request->validate([
                'type' => 'nullable|string|in:question,answer,comment',
                'search' => 'nullable|string|max:100',
                'per_page' => 'nullable|integer|min:5|max:100',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'reason_id' => 'nullable|uuid',
            ]);

            $reportsQuery = Report::with([
                'user:id,username',
                'reason:id,title',
                'reportable'
            ]);

            if ($request->filled('reason_id')) {
                $reportsQuery->where('report_reason_id', $request->query('reason_id'));
            }

            if ($request->filled('type')) {
                $reportsQuery->where('reportable_type', $request->query('type'));
            }

            $status = $request->query('status', 'pending');
            $reportsQuery->where('status', $status);

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $startDate = Carbon::parse($request->query('start_date'))->startOfDay();
                $endDate = Carbon::parse($request->query('end_date'))->endOfDay();
                $reportsQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            if ($request->filled('search')) {
                $search = $request->query('search');
                $reportsQuery->where(function ($query) use ($search) {
                    $query->orWhereHas('user', fn($q) => $q->where('username', 'like', "%{$search}%"))
                        ->orWhereHas('reason', fn($q) => $q->where('title', 'like', "%{$search}%"))
                        ->orWhereHasMorph('reportable', '*', function ($subQuery, $type) use ($search) {
                            if ($type === Question::class) {
                                $subQuery->where('title', 'like', "%{$search}%")->orWhere('question', 'like', "%{$search}%");
                            } elseif ($type === Answer::class) {
                                $subQuery->where('answer', 'like', "%{$search}%");
                            } elseif ($type === Comment::class) {
                                $subQuery->where('comment', 'like', "%{$search}%");
                            }
                        });
                });
            }

            $perPage = $request->query('per_page', 5);
            $reports = $reportsQuery->latest()->paginate($perPage);

            return ReportResource::collection($reports);
        } catch (\Exception $e) {
            Log::error('ReportController@index: Exception occurred.', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['message' => 'An internal server error occurred.'], 500);
        }
    }

    public function processReport(Request $request, Report $report)
    {
        Log::info('ReportController@processReport: Processing started.', ['report_id' => $report->id, 'action' => $request->input('action')]);
        $request->validate(['action' => 'required|string|in:approve,reject']);

        if ($report->status !== 'pending') {
            return response()->json(['message' => 'This report has already been processed.'], 409);
        }

        try {
            $newStatus = ($request->input('action') === 'approve') ? 'resolved' : 'rejected';
            $report->update(['status' => $newStatus, 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

            if ($request->input('action') === 'approve' && $report->reportable) {
                $report->reportable->delete();
            }

            return response()->json(['message' => "Report has been successfully marked as {$newStatus}."]);
        } catch (\Exception $e) {
            Log::error('ReportController@processReport: Failed to process report.', ['report_id' => $report->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to process report.'], 500);
        }
    }

    public function getContentDetail(string $type, string $id)
    {
        Log::info('ReportController@getContentDetail: Request received.', ['type' => $type, 'id' => $id]);
        try {
            $data = null;
            $safeType = strtolower(basename($type));

            switch ($safeType) {
                case 'question':
                    $data = Question::withTrashed()->with([
                        'user:id,username',
                        'answer' => function ($query) {
                            $query->withTrashed()->with([
                                'user:id,username',
                                'comments' => function ($q) { // Menggunakan relasi jamak 'comments'
                                    $q->withTrashed()->with('user:id,username');
                                }
                            ])->withCount('reports as report');
                        },
                        'comments' => function ($query) { // Menggunakan relasi jamak 'comments'
                            $query->withTrashed()->with('user:id,username');
                        }
                    ])
                        ->withCount(['comments', 'reports as report', 'views as view']) // Menggunakan 'comments_count'
                        ->findOrFail($id);
                    break;

                case 'answer':
                    $data = Answer::withTrashed()->with([
                        'user:id,username',
                        'question:id,title',
                        'comments' => function ($query) { // Menggunakan relasi jamak 'comments'
                            $query->withTrashed()->with('user:id,username');
                        }
                    ])
                        ->withCount(['comments', 'reports as report']) // Menggunakan 'comments_count'
                        ->findOrFail($id);
                    break;

                case 'comment':
                    $data = Comment::withTrashed()->with([
                        'user:id,username',
                        'commentable' => function ($morphTo) {
                            $morphTo->withTrashed();
                        }
                    ])
                        ->withCount('reports as report')
                        ->findOrFail($id);

                    if ($data->commentable) {
                        $data->commentable_type = class_basename($data->commentable_type);
                    }
                    break;

                default:
                    return response()->json(['message' => 'Invalid content type provided.'], 400);
            }

            $responseData = $data->toArray();
            $responseData['type'] = $safeType;

            Log::info('ReportController@getContentDetail: Final data structure for response.', ['data' => $responseData]);
            return response()->json($responseData);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Content not found.'], 404);
        } catch (\Exception $e) {
            Log::error("ReportController@getContentDetail: CRITICAL ERROR.", ['message' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
            return response()->json(['message' => 'An internal server error occurred.'], 500);
        }
    }
}
