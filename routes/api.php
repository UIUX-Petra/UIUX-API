<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\SubjectController as AdminSubjectController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ReportReasonController;
use App\Http\Controllers\ReportController;

use App\Models\Question;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::post('/register', [AuthController::class, 'register']);
Route::post('/manualLogin', [AuthController::class, 'manualLogin'])->name('login');
Route::post('/face-login', [AuthController::class, 'faceLogin']);
Route::post('/auth/socialite', [AuthController::class, 'socialiteLogin']);

Route::get('/email/verify-pending/{token}', [AuthController::class, 'verifyPendingEmail'])->name('api.email.verify-pending');
Route::post('/email/resend-pending-verification', [AuthController::class, 'resendPendingVerification']);

Route::get('/email/verify/{user}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])->name('verification.send');

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/userWithRecommendation', [UserController::class, 'getUserWithRecommendation']);
    Route::get('/user-questions/{userId}', [QuestionController::class, 'getUserQuestionsWithCount']);
    Route::apiResource('comments', CommentController::class);
    Route::apiResource('users', UserController::class);

    // Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/face-register', [AuthController::class, 'registerFace']);

    Route::post('/saveQuestion/{email}/{questionId}', [UserController::class, 'saveQuestion']);
    Route::post('/unsaveQuestion/{email}/{questionId}', [UserController::class, 'unsaveQuestion']);
    Route::get('/getSavedQuestions/{email}', [UserController::class, 'getSavedQuestions']);
    Route::get('/questions/{question_id}', [QuestionController::class, 'getQuestionByAnswerId']);
    Route::apiResource('questions', QuestionController::class);
    Route::post('questions/{id}/updatePartial', [QuestionController::class, 'updatePartial']);
    Route::get('/questions-paginated', [QuestionController::class, 'getQuestionPaginated']);
    Route::get('/questions-paginated-home', [QuestionController::class, 'getQuestionPaginatedHome']);

    Route::apiResource('answers', AnswerController::class);
    Route::post('answers/{id}/updatePartial', [AnswerController::class, 'updatePartial']);

    Route::apiResource('tags', SubjectController::class);
    Route::get('/tagOnly', [SubjectController::class, 'tagOnly']);
    Route::get('/tags', [SubjectController::class, 'index']);

    // Route::apiResource('comments', CommentController::class);

    Route::apiResource('users', UserController::class);
    Route::post('/users/{email}/follow', [UserController::class, 'follow']);
    Route::get('/users/{userId}/questions', [UserController::class, 'getUserQuestions']);
    Route::get('/users/get/{email}', [UserController::class, 'getByEmail']);
    Route::get('/userTags', [UserController::class, 'getUserTags']);

    // getUserQuestionsWithCount 
    Route::post('/users/editProfileDULU', [UserController::class, 'editProfileUser']);

    Route::post('questions/{id}/upvote', [QuestionController::class, 'upvoteQuestion']);
    Route::post('questions/{id}/downvote', [QuestionController::class, 'downvoteQuestion']);
    Route::post('answers/{id}/upvote', [AnswerController::class, 'upvoteAnswer']);
    Route::post('answers/{id}/downvote', [AnswerController::class, 'downvoteAnswer']);

    Route::get('/answers-paginated', [AnswerController::class, 'getAnswersPaginated']);

    Route::post('/logout', [AuthController::class, 'logout']);
    // });
    Route::get('/search', [SearchController::class, 'search']);

    Route::apiResource('histories', HistoryController::class);
    Route::post('/history/clear/{email}', [HistoryController::class, 'clearHistory']);
    Route::get('/getDetailedSearchHistory', [HistoryController::class, 'getDetailedSearchHistory']);

    Route::post('questions/{id}/view', [QuestionController::class, 'viewQuestion']);

    Route::get('/getLeaderboardByTag/{id}', [UserController::class, 'getLeaderboardByTag']);
    Route::get('/getMostViewed/{email}', [UserController::class, 'getMostViewed']);

    Route::get('/announcements/active', [AnnouncementController::class, 'getActiveAnnouncements'])->name('announcements.active');
    Route::get('/report-reasons', [ReportReasonController::class, 'getReasons']);
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');

    Route::get('/export-questions-json', function () {
        $questions = Question::select('id', 'title', 'question')->get();
        return response()->json($questions);
    });
});

// ==========================
// GRUP ROUTE UNTUK ADMIN API
// ==========================
Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('/auth/socialite', [AdminAuthController::class, 'socialiteLogin']);
    Route::middleware(['auth:admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::get('dashboard/statistics', [StatisticsController::class, 'getBasicStats'])->name('dashboard.statistics');
        Route::get('dashboard/report-stats', [DashboardController::class, 'getReportStats'])->name('dashboard.reports');

        Route::middleware('role.admin:content-manager,super-admin')->group(function () {
            Route::get('/content-detail/{type}/{id}', [AdminReportController::class, 'getContentDetail'])->name('admin.content.detail');
            Route::apiResource('subjects', AdminSubjectController::class);

            Route::post('questions/{id}', [AdminQuestionController::class, 'update'])->name('questions.update');
            Route::apiResource('questions', AdminQuestionController::class)->except(['update']);
            Route::put('questions/{id}/restore', [AdminQuestionController::class, 'restore'])->name('questions.restore');
            Route::delete('questions/force-delete/{id}', [AdminQuestionController::class, 'forceDelete'])->name('questions.forceDelete');
        });

        Route::middleware('role.admin:moderator,super-admin')->group(function () {
            Route::get('/announcements', [AdminAnnouncementController::class, 'index']);
            Route::post('/announcements', [AdminAnnouncementController::class, 'store']);
            Route::get('/announcements/{announcement}', [AdminAnnouncementController::class, 'showDetail']);
            Route::put('/announcements/{announcement}', [AdminAnnouncementController::class, 'updateDetail']);
            Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroyAnnouncement']);
        });

        Route::middleware('role.admin:user-manager,super-admin')->group(function () {
            Route::get('/users/basic-info', [AdminUserController::class, 'getBasicUserInfo'])->name('users.basic-info');
            Route::post('/users/{user}/block', [AdminUserController::class, 'blockUser'])->name('users.block');
            Route::post('/users/{user}/unblock', [AdminUserController::class, 'unblockUser'])->name('users.unblock');
            Route::get('/users', [AdminUserController::class, 'getBasicUserInfo'])->name('api.admin.users.index');
            Route::get('/users/{user}/activity', [AdminUserController::class, 'getActivitySummary']);
            // Route::get('/content-detail/{type}/{id}', [AdminUserController::class, 'getContentDetail'])->name('content.detail');
        });

        Route::middleware('role.admin:community-manager,super-admin')->group(function () {
            Route::get('/report-reasons', [ReportReasonController::class, 'getReasons']);
            Route::post('/reports/{report}/process', [AdminReportController::class, 'processReport'])->name('reports.process');
            Route::get('/reports', [AdminReportController::class, 'index']);
        });

        Route::middleware('role.admin:super-admin')->group(function () {
            Route::apiResource('roles', RoleController::class);
            Route::get('roles/{role}/admins', [RoleController::class, 'getAssignedAdmins'])->name('roles.admins');
            Route::post('roles/{role}/sync-admins', [RoleController::class, 'syncAdmins'])->name('roles.sync-admins');
            Route::get('admins', [AdminController::class, 'index'])->name('admins.index');
        });
    });
});
