<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class View extends Model
{
    use HasFactory, HasUuids;
    protected $fillable = [
        'user_id',
        'viewable_id',
        'viewable_type',
        'total'
    ];

    protected $hidden = [
        'updated_at',
        'created_at',
        'viewable_id',
        'viewable_type'
    ];
    
    public function viewable()
    {
        return $this->morphTo();
    }
}