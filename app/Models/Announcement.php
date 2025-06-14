<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'admin_id',
        'title',
        'detail',
        'status',
        'display_on_web',
        'published_at',
        'notified_at',
    ];

    protected $casts = [
        'display_on_web' => 'boolean',
        'published_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function getRouteKeyName()
    {
        return 'id'; 
    }
}
