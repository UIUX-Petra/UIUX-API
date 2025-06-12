<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\HasComments;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasMany;



class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasUuids, HasFactory, Notifiable, HasApiTokens, HasComments;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'image',
        'password',
        'biodata',
        'reputation',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'updated_at',
        'password',
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
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmail);
    }


    public function relations()
    {
        return [
            'userAchievement',
            'answer',
            'answer.question.groupQuestion.subject',
            // 'question.comment',
            'question.comment',
            'question.groupQuestion.subject',
            'following',
            'followers',
            'searchedHistory',
            'histories'
        ];
    }
    public function userAchievement()
    {
        return $this->hasMany(UserAchievement::class, 'user_id');
    }
    public function answer()
    {
        return $this->hasMany(Answer::class, 'user_id');
    }
    public function question()
    {
        return $this->hasMany(Question::class, 'user_id');
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followed_id');
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'followed_id', 'follower_id');
    }

    public function savedQuestions()
    {
        return $this->belongsToMany(Question::class, 'saved_questions', 'user_id', 'question_id')
            ->withTimestamps();
    }
    public function searchedHistory() // user sebagai object yang dicari
    {
        return $this->morphMany(History::class, 'searched');
    }
    public function histories()
    {
        return $this->hasMany(History::class); // user -> siapa yang mencari
    }


    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'blocked_user_id');
    }
}
