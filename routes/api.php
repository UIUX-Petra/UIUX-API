<?php

use App\Http\Controllers\AnswerController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuestionController;

Route::get('/', function () {
    return view('welcome');
});

Route::apiResource('questions', QuestionController::class);
Route::get('/questions/{question_id}', [QuestionController::class, 'getQuestionByAnswerId']);

Route::apiResource('answers', AnswerController::class);
Route::get('/answers/{question_id}', [AnswerController::class, 'getAnswerByQuestionId']);

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::middleware(['auth:sanctum'])->group(function () {
    
});