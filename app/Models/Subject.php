<?php

namespace App\Models;

use App\Models\GroupQuestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Validation\Rule;

class Subject extends Model
{
    use HasUuids;

    protected $table = 'tags';

    protected $fillable = [
        'name',
        'description',
        'abbreviation'
    ];

    public static function validationRules($subjectId = null)
    {
        return [
            'name' => [
                'required',
                'string',
                Rule::unique('tags', 'name')->ignore($subjectId),
            ],
            'abbreviation' => [
                'required',
                'string',
                Rule::unique('tags', 'abbreviation')->ignore($subjectId),
            ]
        ];
    }

  
    public static function validationMessages()
    {
        return [
            'name.required' => 'Nama subject tidak boleh kosong.',
            'name.string' => 'Nama subject harus berupa teks.',
            'name.unique' => 'Nama subject ini sudah digunakan.',
            'abbreviation.required' => 'Singkatan tidak boleh kosong.',
            'abbreviation.string' => 'Singkatan harus berupa teks.',
            'abbreviation.unique' => 'Singkatan ini sudah digunakan.',
        ];
    }
    
    public function relations(){
        return [
            'groupQuestion',
            'searchedHistory'
        ];
    }

    public function groupQuestion(){
        return $this->hasMany(GroupQuestion::class, 'tag_id');
    }

    public function searchedHistory()
    {
        return $this->morphMany(History::class, 'searched');
    }
}
