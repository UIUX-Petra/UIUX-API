<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'comment' => $this->comment,
            'timestamp' => $this->created_at,
            'user' => [ 
                'username' => $this->user->username,
                'email' => $this->user->email,
                'image' => $this->user->image,
            ]
        ];
    }
}