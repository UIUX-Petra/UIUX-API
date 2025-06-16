<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\User;
use App\Models\Block;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


class UserController extends BaseController
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function getActivitySummary(User $user)
    {
        // Gunakan eager loading dengan withCount untuk efisiensi
        $user->loadCount(['question', 'answer', 'comments']);

        // Menghitung jumlah laporan terhadap konten milik pengguna
        $questionIds = $user->question()->pluck('id');
        $answerIds = $user->answer()->pluck('id');
        $commentIds = $user->comments()->pluck('id');

        $reportedQuestionsCount = Report::where('reportable_type', Question::class)
                                        ->whereIn('reportable_id', $questionIds)
                                        ->count();
                                        
        $reportedAnswersCount = Report::where('reportable_type', Answer::class)
                                      ->whereIn('reportable_id', $answerIds)
                                      ->count();

        $reportedCommentsCount = Report::where('reportable_type', Comment::class)
                                       ->whereIn('reportable_id', $commentIds)
                                       ->count();
        
        $totalReportsAgainstUser = $reportedQuestionsCount + $reportedAnswersCount + $reportedCommentsCount;

        // Siapkan data statistik
        $stats = [
            'reputation' => $user->reputation,
            'total_questions' => $user->question_count, // Dari loadCount
            'total_answers' => $user->answer_count,     // Dari loadCount
            'total_comments' => $user->comments_count,  // Dari loadCount
            'total_reports_against_user' => $totalReportsAgainstUser,
        ];

        // Gabungkan data user dasar dan statistiknya
        $data = [
            'user' => $user->only(['id', 'username', 'email', 'image', 'created_at']),
            'stats' => $stats,
        ];

        return $this->success('User activity summary retrieved successfully', $data);
    }

     public function getBasicUserInfo(){
        $users = $this->model
            ->withExists('activeBlock')
            ->select('id', 'username', 'image', 'email', 'created_at')
            ->latest()
            ->paginate(10);

        $users->getCollection()->transform(function ($user) {
            $user->status = $user->active_block_exists ? 'Blocked' : 'Active';
            $user->registered_at = $user->created_at->format('M d, Y');
            unset($user->active_block_exists, $user->created_at);

            return $user;
        });

        return $this->success('Successfully retrieved user data', $users);
    }
   public function blockUser(User $user)
    {
        Log::info('Block user request received');
        $adminId = Auth::id();
        Log::info('Block user request received.', [
            'admin_id' => $adminId,
            'user_to_block_id' => $user->id,
            'user_to_block_email' => $user->email,
        ]);

        // Validasi: Cek apakah user sudah diblokir aktif
        $isAlreadyBlocked = Block::where('blocked_user_id', $user->id)
            ->whereNull('unblocker_id')
            ->exists();

        if ($isAlreadyBlocked) {
            Log::warning('Block action failed: User is already blocked.', [
                'admin_id' => $adminId,
                'user_id' => $user->id
            ]);
            return $this->error('This user is already blocked.', 409); // 409 Conflict
        }

        // Buat record blokir baru
        Block::create([
            'blocked_user_id' => $user->id,
            'blocker_id' => $adminId,
        ]);

        Log::info('User successfully blocked.', [
            'admin_id' => $adminId,
            'blocked_user_id' => $user->id
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
