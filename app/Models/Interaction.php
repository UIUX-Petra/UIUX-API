<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'question_id',
        'interaction_type',
    ];

    /**
     * Get the user associated with the interaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the question associated with the interaction.
     */
    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
