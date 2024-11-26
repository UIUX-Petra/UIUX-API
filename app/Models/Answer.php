<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    use HasUuids;

    protected $fillable = [
        'vote',
        'answer',
        'question_id',
        'user_id'
    ];

    protected $hidden = [
        'updated_at',
        'created_at'
    ];

    public static function validationRules(){
        return [
            'vote' => 'required|integer|min:0',
            'answer'=>'required|string'
    ];
    }

    public static function validationMessages(){
        return [
            'vote.required' => 'The total vote field is required.',
            'vote.integer' => 'The total vote must be an integer.',
            'vote.min' => 'The total vote must be at least 0.',

            'answer.required' => 'The answer field is required.',
            'answer.string' => 'The answer must be a valid string.',
        ];
    }

    public function relations(){
        return ['user','question','comment'];
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
}
