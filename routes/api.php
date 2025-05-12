<?php

use App\Http\Controllers\AnswerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SubjectController;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/manualLogin', [AuthController::class, 'manualLogin']);

// Route::get('/email/verify', function () {
//     return view('auth.verify-email');
// })->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify')
    ->middleware('signed'); 

Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
    ->name('verification.send')
    ->middleware(['auth', 'throttle:6,1']); 

Route::get('/userWithRecommendation', [UserController::class, 'getUserWithRecommendation']);
Route::apiResource('questions', QuestionController::class);
Route::get('/questions-paginated', [QuestionController::class, 'getQuestionPaginated']);
Route::get('/user-questions/{userId}', [QuestionController::class, 'getUserQuestionsWithCount']);
Route::apiResource('comments', CommentController::class);
Route::apiResource('users', UserController::class);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/questions/{question_id}', [QuestionController::class, 'getQuestionByAnswerId']);
    
    Route::apiResource('answers', AnswerController::class);
     
    Route::apiResource('tags', SubjectController::class);
    Route::get('/tagOnly', [SubjectController::class,'tagOnly']);

    Route::apiResource('comments', CommentController::class);

    Route::apiResource('users', UserController::class);
    Route::post('/users/{email}/follow', [UserController::class, 'follow']);
    Route::get('/users/{user_id}', [UserController::class, 'getFollower']);
    Route::get('/users/get/{email}', [UserController::class, 'getByEmail']);
    // Route::get('/users/get/{email}', [UserController::class, 'getByEmail']);
    // getUserQuestionsWithCount 
    Route::post('/users/editProfileDULU', [UserController::class, 'editProfileUser']);

    Route::post('questions/{id}/upvote', [QuestionController::class, 'upvoteQuestion']);
    Route::post('questions/{id}/downvote', [QuestionController::class, 'downvoteQuestion']);
    Route::post('answers/{id}/upvote', [AnswerController::class, 'upvoteAnswer']);
    Route::post('answers/{id}/downvote', [AnswerController::class, 'downvoteAnswer']);
});

Route::post('questions/{id}/view', [QuestionController::class, 'viewQuestion']);

Route::get('/getLeaderboardByTag/{id}', [UserController::class,'getLeaderboardByTag']);
Route::get('/getMostViewed/{email}', [UserController::class,'getMostViewed']);
