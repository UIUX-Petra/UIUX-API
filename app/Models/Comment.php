<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasUuids;

    protected $fillable = ['question_id', 'answer_id', 'user_id', 'comment'];

    protected $hidden = [
        'updated_at',
        'created_at'
    ];

    public static function validationRules()
    {
        return ['comment' => 'required|string'];
    }

    public static function validationMessages()
    {
        return [
            'comment.required' => 'The comment field is required.',
            'comment.string' => 'The comment must be a valid string.',
        ];
    }

    public function relations()
    {
        return ['user'];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
