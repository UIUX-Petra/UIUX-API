<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['admin_id', 'title', 'detail', 'status'];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}