<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'question' => $this->question,
            'image' => $this->image,
            'vote' => $this->vote,
            'view' => $this->view,
            'user_id' => $this->user_id,
            'timestamp' => $this->created_at,
            'group_question' => $this->groupQuestion,
            
            
            'is_saved_by_request_user' => $this->when(Auth::check(), function () {
                return $this->savedByUsers()->where('user_id', Auth::id())->exists();
            }),

            'user' => [
                'username' => $this->user->username,
                'email' => $this->user->email,
                'image' => $this->user->image,
            ],
            'answers' => AnswerResource::collection($this->whenLoaded('answer')),
            'comments' => CommentResource::collection($this->whenLoaded('comment')),
            'comment_count' => $this->comment->count(), 
        ];
    }
}