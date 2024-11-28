<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasUuids, HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'biodata',
        'reputation'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public static function validationRules()
    {
        return [
            'username' => 'required|string|unique:users,username',
            'email' => 'required|email|regex:/@gmail.com$/|unique:users,email',
            'biodata' => 'nullable|string',
            'reputation' => 'integer'
        ];
    }
    public static function validationMessages()
    {
        return [
            'username.required' => 'The username is required.',
            'username.string' => 'The username must be a valid string.',
            'username.unique' => 'This username is already taken. Please use a different one.',

            'email.required' => 'The email address is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.regex' => 'The email must be a Gmail address (e.g., example@gmail.com).',
            'email.unique' => 'This email address is already taken. Please use a different one.',

            'biodata.string' => 'The biodata must be a valid string.',

            'reputation.integer' => 'The reputation must be a valid integer.',
        ];
    }

    public function relations()
    {
        return [
            'userAchievement',
            'answer',
            'comment',
            'question'
        ];
    }
    public function userAchievement()
    {
        return $this->hasMany(userAchievement::class, 'user_id');
    }
    public function answer()
    {
        return $this->hasMany(Answer::class, 'user_id');
    }
    public function comment()
    {
        return $this->hasMany(Comment::class, 'user_id');
    }
    public function question()
    {
        return $this->hasMany(Question::class, 'user_id');
    }
}
