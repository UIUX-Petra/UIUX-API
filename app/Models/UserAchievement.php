<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserAchievement extends Model
{
    use HasUuids;
    
    protected $hidden = [
        'updated_at',
        'created_at'
    ];

    public function relations(){
        return [];
    }

    public function achievement(){

    }
    public function user(){
        
    }
}
