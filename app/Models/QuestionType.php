<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class QuestionType extends Model
{
    use HasUuids;
    protected $table = 'question_subjects';

    protected $hidden = [
        'updated_at',
        'created_at'
    ];
    
    public function relations(){
        return [];
    }
}
