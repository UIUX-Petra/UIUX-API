<?php

namespace App\Traits;

use App\Models\Vote;
use Illuminate\Support\Facades\DB;

trait HasVotes
{
    protected array $lastVoteOutcome;

    public function votes()
    {
        return $this->morphMany(Vote::class, 'votable');
    }

    public function getVoteFromUser($userId)
    {
        return $this->votes()->where('user_id', $userId)->first();
    }

    public function processVote($userId, $voteType)
    {
        if (!in_array($voteType, [Vote::TYPE_UPVOTE, Vote::TYPE_DOWNVOTE])) {
            throw new \InvalidArgumentException('Invalid vote type.');
        }

        $this->lastVoteOutcome = ['changed' => false, 'message' => '', 'new_score' => $this->vote];

        DB::transaction(function () use ($userId, $voteType) {
            $existingVote = $this->getVoteFromUser($userId);
            $originalScore = $this->vote;

            $scoreChange = 0;
            $actionMessage = '';
            $voteStateChanged = false;

            if ($existingVote) {
                if ($existingVote->type == $voteType) {
                    $actionMessage = 'You have already voted this way.';
                } else {
                    $scoreChange = $voteType - $existingVote->type;
                    $existingVote->update(['type' => $voteType]);
                    $actionMessage = 'Vote changed successfully.';
                    $voteStateChanged = true;
                }
            } else {
                $this->votes()->create([
                    'user_id' => $userId,
                    'type'    => $voteType,
                ]);
                $scoreChange = $voteType;
                $actionMessage = 'Vote registered successfully.';
                $voteStateChanged = true;
            }

            if ($scoreChange !== 0) {
                $this->increment('vote', $scoreChange);
            }

            $newScore = $originalScore + $scoreChange;
            $this->checkAndUpdateReputation($originalScore, $newScore);

            $this->lastVoteOutcome = [
                'changed' => $voteStateChanged,
                'message' => $actionMessage,
                'new_score' => $newScore
            ];
        });

        return $this;
    }

    // public function getLastVoteOutcome(): array
    // {
    //     return $this->lastVoteOutcome ?? ['changed' => false, 'message' => 'No vote action recently performed.', 'new_score' => $this->vote];
    // }

    /**
     * Upvote the item.
     */
    public function upvote($userId)
    {
        return $this->processVote($userId, Vote::TYPE_UPVOTE);
    }

    /**
     * Downvote the item.
     */
    public function downvote($userId)
    {
        return $this->processVote($userId, Vote::TYPE_DOWNVOTE);
    }

    // public function resetVotes()
    // {
    //     DB::transaction(function () {
    //         $originalScore = $this->vote;
    //         $this->votes()->delete();
    //         $this->update(['vote' => 0]);
    //         $this->checkAndUpdateReputation($originalScore, 0);
    //     });
    //     $this->lastVoteOutcome = ['changed' => true, 'message' => 'Votes reset.', 'new_score' => 0];
    //     return $this;
    // }

    protected function checkAndUpdateReputation($previousVote, $currentVote)
    {
        if (!$this->relationLoaded('user')) {
             $this->load('user');
        }
        $contentAuthor = $this->user;

        if (!$contentAuthor) {
            return;
        }

        $threshold = 10;
        if ($previousVote < $threshold && $currentVote >= $threshold) {
            $contentAuthor->increment('reputation');
        } elseif ($previousVote >= $threshold && $currentVote < $threshold) {
            if ($contentAuthor->reputation > 0) {
                $contentAuthor->decrement('reputation');
            }
        }
    }

    public function hasVoted($userId): bool
    {
        return $this->votes()->where('user_id', $userId)->exists();
    }

    public function getUserVoteType($userId): ?int
    {
        $vote = $this->getVoteFromUser($userId);
        return $vote ? $vote->type : null;
    }
}