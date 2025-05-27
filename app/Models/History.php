<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class History extends Model
{
    use HasUuids;

    protected $fillable = [
        'searched_id',
        'searched_type',
        'user_id'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public static function validationRules()
    {
        return [
            'searched_id' => 'required|string',
            'searched_type' => 'required|string|in:question,answer,user',
            'user_id' => 'required|string',
        ];
    }


    public static function validationMessages()
    {
        return [
            'searched_id.required' => 'The ID of the searched entity is required.',
            'searched_id.string' => 'The searched entity ID must be a string.',

            'searched_type.required' => 'The type of the searched entity is required.',
            'searched_type.in' => 'The searched type must be one of: question, answer, user.',

            'user_id.required' => 'The user ID is required.',
            'user_id.string' => 'The user ID must be a string.',
        ];
    }
    public function relations(): array
    {
        return ['searched'];
    }
    public function searched()
    {
        return $this->morphTo();
    }
}
