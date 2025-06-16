<?php

namespace App\Models;

use App\Traits\HasReports;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasUuids, HasReports;

    protected $fillable = [
        'user_id',
        'comment',
        'commentable_id',
        'commentable_type'
    ];

    protected $hidden = [
        'updated_at',
        // 'created_at',
        // 'commentable_id',
        // 'commentable_type'
    ];

    public static function validationRules()
    {
        return [
            'comment' => 'required|string',
            'commentable_id' => 'required|uuid',
            'commentable_type' => 'required|string|in:question,answer',
        ];
    }

    public static function validationMessages()
    {
        return [
            'comment.required' => 'The comment field is required.',
            'comment.string' => 'The comment must be a valid string.',
            'commentable_id.required' => 'The parent ID is required.',
            'commentable_type.required' => 'The parent type (question/answer) is required.',
            'commentable_type.in' => 'The parent type must be either "question" or "answer".',
        ];
    }

    public function relations()
    {
        return ['user', 'commentable'];
    }

    public function commentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
