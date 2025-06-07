<?php

namespace App\Traits;

use App\Models\Comment;

trait HasComments
{
    public function comment()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}