<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\User;
use App\Models\Answer;
use App\Models\Comment;
use App\Models\Question;
use App\Models\Block;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class UserController extends BaseController
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function getActivitySummary(User $user)
    {
        $user->loadCount(['question', 'answer', 'comment']);

        $questionIds = $user->question()->pluck('id');
        $answerIds = $user->answer()->pluck('id');
        $commentIds = $user->comment()->pluck('id');

        $reportedQuestionsCount = Report::where('reportable_type', 'question')->whereIn('reportable_id', $questionIds)->count();
        $reportedAnswersCount = Report::where('reportable_type', 'answer')->whereIn('reportable_id', $answerIds)->count();
        $reportedCommentsCount = Report::where('reportable_type', 'comment')->whereIn('reportable_id', $commentIds)->count();

        $stats = [
            'reputation' => $user->reputation,
            'total_questions' => $user->question_count,
            'total_answers' => $user->answer_count,
            'total_comments' => $user->comment_count,
            'total_reports_against_user' => $reportedQuestionsCount + $reportedAnswersCount + $reportedCommentsCount,
        ];

        $recent_activities = [
            'questions' => $user->question()
                ->latest()
                ->take(5)
                ->get(['id', 'title', 'created_at', 'vote', 'view']),
            'answers' => $user->answer()
                ->with('question:id,title')
                ->latest()
                ->take(5)
                ->get(['id', 'answer', 'question_id', 'created_at']),
            'comments' => $user->comment()
                ->with('commentable')
                ->latest()
                ->take(5)
                ->get(['id', 'comment', 'commentable_type', 'commentable_id', 'created_at']),
        ];

        $data = [
            'user' => $user->only(['id', 'username', 'email', 'image', 'reputation', 'created_at']),
            'stats' => $stats,
            'activities' => $recent_activities,
        ];

        return $this->success('User activity summary retrieved successfully', $data);
    }


    public function getBasicUserInfo(Request $request)
    {
        $query = $this->model
            ->with('activeBlock')
            ->select('id', 'username', 'image', 'email', 'created_at');

        $query->when($request->query('status') === 'active', function ($q) {
            return $q->whereDoesntHave('activeBlock');
        });

        $query->when($request->query('status') === 'blocked', function ($q) {
            return $q->whereHas('activeBlock');
        });

        $query->when($request->filled('search'), function ($q) use ($request) {
            $searchTerm = $request->query('search');
            return $q->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('id', '=', $searchTerm)
                    ->orWhere('username', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('email', 'LIKE', "%{$searchTerm}%");
            });
        });


        $users = $query->latest()->paginate(10);

        $users->getCollection()->transform(function ($user) {
            $user->status = $user->activeBlock ? 'Blocked' : 'Active';
            if ($user->activeBlock) {
                // Jika diblokir, cek ada end_time, kalau ga, blokir permanen.
                $user->end_time = $user->activeBlock->end_time ? \Carbon\Carbon::parse($user->activeBlock->end_time)->format('M d, Y') : 'Permanently';
            } else {
                // Jika tidak diblokir, isi -
                $user->end_time = '-';
            }
            $user->registered_at = $user->created_at->format('M d, Y');
            unset($user->activeBlock, $user->created_at);

            return $user;
        });

        return $this->success('Successfully retrieved user data', $users);
    }
    public function blockUser(User $user, Request $request)
    {
        $adminId = Auth::id();

        $validator = Validator::make($request->all(), [
            'end_time' => ['nullable', 'date', 'after:today'],
        ]);

        if ($validator->fails()) {
            return $this->error(
                'Invalid unblock date: ' . $validator->errors()->first('end_time'),
                422
            );
        }

        $endTime = $request->input('end_time');

        $isAlreadyBlocked = Block::where('blocked_user_id', $user->id)
            ->whereNull('unblocker_id')
            ->where(function ($q) {
                $q->whereNull('end_time')
                    ->orWhere('end_time', '>', now());
            })
            ->exists();

        if ($isAlreadyBlocked) {
            Log::warning('Block action failed: already blocked.', [
                'admin_id' => $adminId,
                'user_id' => $user->id,
            ]);
            return $this->error('This user is already blocked.', 409);
        }

        $block = Block::create([
            'blocked_user_id' => $user->id,
            'blocker_id' => $adminId,
            'end_time' => $endTime,
        ]);

        Log::info('User successfully blocked.', [
            'admin_id' => $adminId,
            'blocked_user_id' => $user->id,
            'block_id' => $block->id,
            'end_time' => $endTime,
        ]);

        return $this->success('User has been successfully blocked.');
    }


    public function unblockUser(User $user)
    {
        $adminId = Auth::id();
        Log::info('Unblock user request received.', [
            'admin_id' => $adminId,
            'user_to_unblock_id' => $user->id,
            'user_to_unblock_email' => $user->email,
        ]);

        $activeBlock = Block::where('blocked_user_id', $user->id)
            ->whereNull('unblocker_id')
            ->first();

        if (!$activeBlock) {
            Log::warning('Unblock action failed: User is not currently blocked.', [
                'admin_id' => $adminId,
                'user_id' => $user->id
            ]);
            return $this->error('This user is not currently blocked.', 404); // 404 Not Found
        }

        $activeBlock->unblocker_id = $adminId;
        $activeBlock->save();

        Log::info('User successfully activated.', [
            'admin_id' => $adminId,
            'activated_user_id' => $user->id,
            'block_record_id' => $activeBlock->id,
        ]);

        return $this->success('User has been successfully activated.');
    }
}
