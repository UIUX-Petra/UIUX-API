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
        'subject_id',
        'group_question_id',
        'user_id'
    ];

    protected $hidden = [
        'updated_at',
        'created_at'
    ];

    public static function validationRules()
    {
        return [
            'vote' => 'integer',
            'title' => 'required|string',
            'image' => 'nullable|file|mimes:png,jpg,jpeg|max:5120', //5MB
            'question' => 'required|string'
        ];
    }

    public static function validationMessages()
    {
        return [
            'vote.integer' => 'The total vote must be an integer.',
            'view.integer' => 'The total view must be an integer.',

            'image.file' => 'The uploaded file must be a valid file.',
            'image.mimes' => 'The image must be a file of type: png, jpg, jpeg.',
            'image.max' => 'The image size must not exceed 5MB.',

            'title.required' => 'The title field is required.',
            'title.string' => 'The title must be a valid string.',
            'question.required' => 'The question field is required.',
            'question.string' => 'The question must be a valid string.',
        ];
    }

    public function relations()
    {
        return [
            "subject",
            "answer",
            "comment",
            "user",
            "groupQuestion"
        ];
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
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
        return $this->belongsTo(GroupQuestion::class, 'group_question_id');
    }

    public function votes()
    {
        return $this->morphMany(Vote::class, 'votable');
    }

    public function views(){
        return $this->morphMany(View::class,'viewable');
    }
}
