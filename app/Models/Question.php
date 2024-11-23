<?php

namespace App\Models;

use App\Models\QuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Question extends Model
{
    use HasUuids;
    protected $fillable = [
        'vote',
        'image',
        'question'
    ];

    protected $hidden = [
        'updated_at',
        'created_at'
    ];

    public static function validationRules()
    {
        return [
            'vote' => 'required|integer|min:0',
            'image' => 'nullable|file|mimes:png,jpg,jpeg|max:5120', //5MB
            'question' => 'required|string'
        ];
    }

    public static function validationMessages()
    {
        return [
            'vote.required' => 'The total vote field is required.',
            'vote.integer' => 'The total vote must be an integer.',
            'vote.min' => 'The total vote must be at least 0.',

            'image.file' => 'The uploaded file must be a valid file.',
            'image.mimes' => 'The image must be a file of type: png, jpg, jpeg.',
            'image.max' => 'The image size must not exceed 5MB.',

            'question.required' => 'The question field is required.',
            'question.string' => 'The question must be a valid string.',
        ];
    }

    //Relationships

    public function relations(){
        return [
        "questionType",
        "answers",
        "comments",
        "user",
        "groupQuestions"
    ];
    }
    
    public function questionType()
    {
        return $this->hasMany(QuestionType::class, 'question_id');
    }

    public function answers()
    {
        return $this->hasMany(Answer::class, 'question_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'question_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function groupQuestions()
    {
        return $this->belongsTo(GroupQuestion::class, 'group_id');
    }

}
