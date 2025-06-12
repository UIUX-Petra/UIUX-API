<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Model
{
    use HasFactory, HasUuids, HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'nrp',
        'email',
        'password',
    ];
    
    protected $hidden =['password'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_role');
    }
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function blockedRecords(): HasMany
    {
        return $this->hasMany(Block::class, 'blocker_id');
    }

    public function unblockedRecords(): HasMany
    {
        return $this->hasMany(Block::class, 'unblocker_id');
    }
}
