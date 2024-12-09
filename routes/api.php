<?php

use App\Http\Controllers\AnswerController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\RecommendationController;

Route::get('/', function () {
    return view('welcome');
});

Route::apiResource('questions', QuestionController::class);
Route::get('/questions/{question_id}', [QuestionController::class, 'getQuestionByAnswerId']);

Route::apiResource('answers', AnswerController::class);
Route::get('/answers/{question_id}', [AnswerController::class, 'getAnswerByQuestionId']);

Route::apiResource('users', UserController::class);
Route::post('/users/{email}/follow', [UserController::class, 'follow']);
Route::get('/users/{user_id}', [UserController::class, 'getFollower']);
Route::get('/users/get/{email}', [UserController::class, 'getByEmail']); //pindah sini saja dripada dari show-nya basecontroller


// LOGIN
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register'])->name('register');

// verification email
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify')
    ->middleware('signed'); 

Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
    ->name('verification.send')
    ->middleware(['auth', 'throttle:6,1']); 

Route::post('/manualLogin', [AuthController::class, 'manualLogin'])->name('manualLogin');
Route::middleware(['auth:sanctum'])->group(function () {});
Route::post('/register', [AuthController::class,'register'])->name('register');
Route::post('/manualLogin', [AuthController::class,'manualLogin'])->name('manualLogin');

Route::get('/recommend', [RecommendationController::class, 'recommend'])->name('recommend');
Route::middleware(['auth:sanctum'])->group(function () {
    
});
