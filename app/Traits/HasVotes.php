<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait HasVotes
{
    public function hasVoted($userId)
    {
        return $this->votes()->where('user_id', $userId)->exists();
    }

    /**
     * Increment the vote count and update user reputation if necessary.
     */
    public function upvote($userId)
    {
        if ($this->hasVoted($userId)) {
            throw new \Exception('User has already voted on this item.');
        }
        $originalVote = $this->vote;
        $this->votes()->create(['user_id' => $userId]);
        $this->increment('vote');
        $this->checkAndUpdateReputation($originalVote, $this->vote);
        return $this;
    }

    /**
     * Decrement the vote count and update user reputation if necessary.
     */
    public function downvote($userId)
    {
        if ($this->hasVoted($userId)) {
            throw new \Exception('User has already voted on this item.');
        }
        $originalVote = $this->vote;
        $this->votes()->create(['user_id' => $userId]);
        $this->decrement('vote');
        $this->checkAndUpdateReputation($originalVote, $this->vote);
        return $this;
    }

    /**
     * Reset the vote count to zero.
     */
    public function resetVotes()
    {
        $originalVote = $this->vote;
        $this->update(['vote' => 0]);
        $this->checkAndUpdateReputation($originalVote, 0);
        return $this;
    }

    /**
     * Check if the vote threshold is crossed and update the user's reputation.
     */
    protected function checkAndUpdateReputation($previousVote, $currentVote)
    {
        $user = $this->user;
        if (!$user) {
            return;
        }
        $threshold = 10;
        if ($previousVote < $threshold && $currentVote >= $threshold) {
            $user->increment('reputation');
        } elseif ($previousVote >= $threshold && $currentVote < $threshold) {
            $user->decrement('reputation');
        }
    }
}