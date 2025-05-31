<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PendingUser extends Model
{
    use HasUuids;

    protected $fillable = [
        'username',
        'email',
        'password',
        'verification_token',
        'existing_user_id',
        'expires_at',
    ];

    protected $hidden = [
        'password',
        'verification_token',
    ];
}
