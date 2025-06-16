<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SubjectController as AdminSubjectController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SubjectController;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::post('/register', [AuthController::class, 'register']);
Route::post('/manualLogin', [AuthController::class, 'manualLogin']);
Route::post('/auth/socialite', [AuthController::class, 'socialiteLogin']);

Route::get('/email/verify-pending/{token}', [AuthController::class, 'verifyPendingEmail'])->name('api.email.verify-pending');
Route::post('/email/resend-pending-verification', [AuthController::class, 'resendPendingVerification']);

Route::get('/email/verify/{user}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])->name('verification.send');

Route::get('/userWithRecommendation', [UserController::class, 'getUserWithRecommendation']);
Route::get('/user-questions/{userId}', [QuestionController::class, 'getUserQuestionsWithCount']);
Route::apiResource('comments', CommentController::class);
// Route::apiResource('users', UserController::class);

Route::middleware(['auth:sanctum'])->group(function () {
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

    Route::apiResource('comments', CommentController::class);

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
});
Route::get('/search', [SearchController::class, 'search']);

Route::apiResource('histories', HistoryController::class);
Route::post('/history/clear/{email}', [HistoryController::class, 'clearHistory']);
Route::get('/getDetailedSearchHistory', [HistoryController::class, 'getDetailedSearchHistory']);

Route::post('questions/{id}/view', [QuestionController::class, 'viewQuestion']);

Route::get('/getLeaderboardByTag/{id}', [UserController::class, 'getLeaderboardByTag']);
Route::get('/getMostViewed/{email}', [UserController::class, 'getMostViewed']);


// ==========================
// GRUP ROUTE UNTUK ADMIN API
// ==========================
Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('/auth/socialite', [AdminAuthController::class, 'socialiteLogin']);
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/reports/{report}/process', [\App\Http\Controllers\Admin\ReportController::class, 'processReport'])->name('reports.process');
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/users/basic-info', [AdminUserController::class, 'getBasicUserInfo'])->name('users.basic-info');
        Route::post('/users/{user}/block', [AdminUserController::class, 'blockUser'])->name('users.block');
        Route::post('/users/{user}/unblock', [AdminUserController::class, 'unblockUser'])->name('users.unblock');
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);

        // announcements
        Route::get('/announcements', [AnnouncementController::class, 'index']);
        Route::post('/announcements', [AnnouncementController::class, 'store']);
        Route::get('/announcements/{announcement}', [AnnouncementController::class, 'showDetail']);
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'updateDetail']);
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroyAnnouncement']);

        Route::apiResource('subjects', AdminSubjectController::class);

        Route::apiResource('roles', RoleController::class);
        Route::get('roles/{role}/admins', [RoleController::class, 'getAssignedAdmins'])->name('roles.admins');
        Route::post('roles/{role}/sync-admins', [RoleController::class, 'syncAdmins'])->name('roles.sync-admins');

        Route::get('admins', [AdminController::class, 'index'])->name('admins.index');

        Route::get('dashboard/report-stats', [DashboardController::class, 'getReportStats'])->name('dashboard.reports');
    });
});
