<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GroupQuestion extends Model
{
    //
    use HasUuids;

    protected $table = 'subject_questions';

    protected $fillable = ['question_id', 'tag_id'];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public function relations()
    {
        return ['subject', 'question'];
    }
    public function subject()
    {
        return $this->belongsTo(subject::class, 'tag_id');
    }
    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
