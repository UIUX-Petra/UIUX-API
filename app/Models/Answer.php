<?php

namespace App\Models;

use App\Traits\HasVotes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    use HasUuids, HasVotes;

    protected $fillable = [
        'vote',
        'answer',
        'image',
        'question_id',
        'user_id',
        'verified'
    ];

    protected $hidden = [
        'updated_at',
        // 'created_at'
    ];

    public static function validationRules()
    {
        return [
            'vote' => 'integer',
            'answer' => 'required|string',
        ];
    }

    public static function validationMessages()
    {
        return [
            'vote.integer' => 'The total vote must be an integer.',

            'answer.required' => 'The answer field is required.',
            'answer.string' => 'The answer must be a valid string.',
        ];
    }

    public function relations()
    {
        return ['user', 'question', 'comment'];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
    public function comment()
    {
        return $this->hasMany(Comment::class, 'answer_id');
    }
    public function votes()
    {
        return $this->morphMany(Vote::class, 'votable');
    }
}
