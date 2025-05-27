<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'name',
        'detail'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public static function validationRules(){
        return [
            'name'=>'required|string',
            'detail'=>'required|string'
        ];
    }

    public static function validationMessages(){
        return [
            'name.required' => 'The Achievement Name field is required.',
            'name.string' => 'The Achievement Name must be a valid string.',

            'detail.required' => 'The Detail field is required.',
            'detail.string' => 'The Detail must be a valid string.',
        ];
    }
    public function relations(){
        return ['userAchievement'];
    }
    public function userAchievement(){
        return $this->hasMany(UserAchievement::class, 'achievement_id');
    }
    
}
