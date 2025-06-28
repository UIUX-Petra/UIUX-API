<?php

namespace App\Models;

use App\Traits\HasComments;
use App\Traits\HasReports;
use App\Traits\HasVotes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;



class Answer extends Model
{
    use HasUuids, HasVotes, HasComments, HasReports, SoftDeletes;

    protected $fillable = [
        'vote',
        'answer',
        'image',
        'question_id',
        'user_id',
        'verified',

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
        return ['user', 'question', 'comment', 'user.histories'];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }


    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return Storage::disk('public')->url($this->image);
        }
        return null;
    }

  public function comments()
{
    return $this->morphMany(Comment::class, 'commentable');
}
}
