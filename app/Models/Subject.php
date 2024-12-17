<?php

namespace App\Models;

use App\Models\GroupQuestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Subject extends Model
{

    use HasUuids;

    protected $table = 'tags';

    protected $fillable = [
        'name',
        'description'
    ];

    public static function validationRules()
    {
        return [
            'name'=>'required|string'
        ];
    }

    public static function validationMessages()
    {
        return [
            'name.required' => 'The subject field is required.',
            'name.string' => 'The subject must be a valid string.',
        ];
    }
    
    public function relations(){
        return [
            'groupQuestion'
        ];
    }

    public function groupQuestion(){
        return $this->hasMany(GroupQuestion::class, 'subject_id');
    }
}
