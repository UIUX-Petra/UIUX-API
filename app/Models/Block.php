<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Block extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['blocker_id', 'unblocker_id', 'blocked_user_id', 'end_time'];

    protected $casts = [
        'end_time' => 'datetime',
    ];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'blocker_id');
    }

    public function unblocker(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'unblocker_id');
    }

    public function blockedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }
}