<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GroupQuestion extends Model
{
    //
    use HasUuids;

    protected $fillable = ['type'];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public static function validationRules()
    {
        return ['type' => 'required|in:0,1,2,3'];
    }
    public static function validationMassages()
    {
        return [
            'type.required' => 'The type field is required.',
            'type.in' => 'The type field must be one of the following: 0, 1, 2, or 3.'
        ];
    }

    public function relations()
    {
        return ['subject','question'];
    }
    public function subject()
    {
        return $this->belongsTo(subject::class, 'subject_id');
    }
    public function question()
    {
        return $this->hasMany(Question::class, 'group_id');
    }
}
