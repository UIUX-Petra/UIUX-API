<?php

use App\Http\Controllers\AnswerController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\UserController;

Route::get('/', function () {
    return view('welcome');
});

Route::apiResource('questions', QuestionController::class);
Route::get('/questions/{question_id}', [QuestionController::class, 'getQuestionByAnswerId']);

Route::apiResource('answers', AnswerController::class);
Route::get('/answers/{question_id}', [AnswerController::class, 'getAnswerByQuestionId']);

Route::apiResource('users', UserController::class);
Route::post('/users/{user_id}/follow', [UserController::class, 'follow']);
Route::get('/users/{user_id}', [UserController::class, 'getFollower']);

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::middleware(['auth:sanctum'])->group(function () {
    
});