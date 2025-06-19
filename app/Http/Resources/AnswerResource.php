<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'answer' => $this->answer,
            'image' => $this->image,
            'vote' => $this->vote,
            'verified' => $this->verified,
            'timestamp' => $this->created_at,
            'user' => [
                'username' => $this->user->username,
                'email' => $this->user->email,
                'image' => $this->user->image,
            ],
            'comments' => CommentResource::collection($this->whenLoaded('comment')),
        ];
    }
}