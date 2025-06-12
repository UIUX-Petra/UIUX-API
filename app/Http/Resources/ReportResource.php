<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Logika untuk menentukan tipe dan judul dari konten yang dilaporkan
        $relatedToType = match ($this->reportable_type) {
            \App\Models\Question::class => 'Question',
            \App\Models\Answer::class   => 'Answer',
            \App\Models\Comment::class  => 'Comment',
            default => 'Unknown',
        };

        // Mengambil teks utama dari konten terkait
        $relatedToTitle = $this->reportable->title ?? $this->reportable->answer ?? $this->reportable->comment ?? 'Content not available';

        return [
            'id' => $this->id,
            'preview' => $this->preview,
            'reason' => $this->reason,
            'status' => $this->status,
            'date_reported' => $this->created_at->diffForHumans(),
            'reporter' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'related_to' => [
                'type' => $relatedToType,
                'id' => $this->reportable->id,
                // Batasi panjang judul/teks agar tidak terlalu panjang di API response
                'title' => substr($relatedToTitle, 0, 50) . (strlen($relatedToTitle) > 50 ? '...' : ''),
            ],
        ];
    }
}