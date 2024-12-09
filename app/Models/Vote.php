<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'votable_id', 'votable_type'];

    public function votable()
    {
        return $this->morphTo();
    }
}
