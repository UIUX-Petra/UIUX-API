<?php

use App\Http\Controllers\AnswerController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RecommendationController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/manualLogin', [AuthController::class, 'manualLogin']);

Route::get('/userWithRecommendation', [UserController::class, 'getUserWithRecommendation']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('questions', QuestionController::class);
    Route::get('/questions/{question_id}', [QuestionController::class, 'getQuestionByAnswerId']);

    Route::apiResource('answers', AnswerController::class);
    Route::get('/answers/{question_id}', [AnswerController::class, 'getAnswerByQuestionId']);

    Route::apiResource('users', UserController::class);
    Route::post('/users/{id}/follow', [UserController::class, 'follow']);
    Route::get('/users/{user_id}', [UserController::class, 'getFollower']);
    Route::get('/users/get/{email}', [UserController::class, 'getByEmail']);
});