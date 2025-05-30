<?php

namespace App\Models;

use App\Traits\HasViews;
use App\Traits\HasVotes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Question extends Model
{
    use HasUuids, HasVotes, HasViews;
    protected $fillable = [
        'vote',
        'title',
        'image',
        'question',
        'user_id'
    ];

    protected $hidden = [
        'updated_at'
    ];

    public static function validationRules()
    {
        return [
            'vote' => 'integer',
            'title' => 'required|string',
            'view' => 'integer',
            'question' => 'required|string'
        ];
    }

    public static function validationMessages()
    {
        return [
            'vote.integer' => 'The total vote must be an integer.',
            'view.integer' => 'The total view must be an integer.',

            'title.required' => 'The title field is required.',
            'title.string' => 'The title must be a valid string.',
            'question.required' => 'The question field is required.',
            'question.string' => 'The question must be a valid string.',
        ];
    }

    public function relations()
    {
        return [
            "answer",
            "comment",
            "user",
            "groupQuestion",
            "savedByUsers"
        ];
    }

    public function getDefaultRelations()
    {
        return [
            "answer",
            "comment",
            "user",
            "groupQuestion.subject",
            "searchedHistory"
        ];
    }

    public function answer()
    {
        return $this->hasMany(Answer::class, 'question_id');
    }

    public function comment()
    {
        return $this->hasMany(Comment::class, 'question_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function groupQuestion()
    {
        return $this->hasMany(GroupQuestion::class, 'question_id');
    }

    public function votes()
    {
        return $this->morphMany(Vote::class, 'votable');
    }

    public function views()
    {
        return $this->morphMany(View::class, 'viewable');
    }

    public function savedByUsers()
    {
        return $this->belongsToMany(User::class, 'saved_questions', 'question_id', 'user_id')
            ->withTimestamps();
    }
    public function searchedHistory()
    {
        return $this->morphMany(History::class, 'searched');
    }
}
