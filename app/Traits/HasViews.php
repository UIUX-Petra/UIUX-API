<?php

namespace App\Traits;

trait HasViews
{
    public function hasViewed($userId)
    {
        return $this->views()->where("user_id", $userId)->exists();
    }

    public function view($userId)
    {
        if ($this->hasViewed($userId)) {
            $this->views()->where('user_id', $userId)->first()->increment('total');
            throw new \Exception('User has already voted on this item.');
        }
        $this->views()->create(['user_id' => $userId, 'total' => 1]);
        $this->increment('view');
        return $this;
    }
}
