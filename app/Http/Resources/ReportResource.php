<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reportable = $this->whenLoaded('reportable');
        $parentContent = null;
        $reportedContentUrl = '#';

        if ($reportable) {
            $type = $reportable->getMorphClass();

            // Handle URL dan konten induk berdasarkan tipe, dengan pengecekan route
            switch ($type) {
                case 'question':
                    if (Route::has('questions.show')) {
                        $reportedContentUrl = route('questions.show', $reportable->id);
                    }
                    break;

                case 'answer':
                    if ($reportable->question && Route::has('questions.show')) {
                        $questionUrl = route('questions.show', $reportable->question_id);
                        $reportedContentUrl = $questionUrl . '#answer-' . $reportable->id;
                        $parentContent = [
                            'type' => 'Question',
                            'id' => $reportable->question->id,
                            'title' => $reportable->question->title,
                            'url' => $questionUrl,
                        ];
                    }
                    break;

                case 'comment':
                    if ($reportable->commentable && Route::has('questions.show')) {
                        $parent = $reportable->commentable;
                        $parentType = $parent->getMorphClass();

                        if ($parentType === 'question') {
                            $questionUrl = route('questions.show', $parent->id);
                            $reportedContentUrl = $questionUrl . '#comment-' . $reportable->id;
                            $parentContent = [
                                'type' => 'Question',
                                'id' => $parent->id,
                                'title' => $parent->title,
                                'url' => $questionUrl,
                            ];
                        } elseif ($parentType === 'answer' && $parent->question) {
                            $questionUrl = route('questions.show', $parent->question_id);
                            $reportedContentUrl = $questionUrl . '#comment-' . $reportable->id;
                            $parentContent = [
                                'type' => 'Answer',
                                'id' => $parent->id,
                                'title' => 'Answer to: ' . $parent->question->title,
                                'url' => $questionUrl . '#answer-' . $parent->id,
                            ];
                        }
                    }
                    break;
            }
        }

        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'date_reported' => $this->created_at->format('d M Y, H:i'),
            'date_for_humans' => $this->created_at->diffForHumans(),

            'reported_content' => [
                'type' => $reportable ? $reportable->getMorphClass() : 'deleted',
                'id' => $reportable ? $reportable->id : null,
                'preview' => $reportable ? Str::limit($reportable->content ?? $reportable->title, 150) : 'Content has been deleted.',
                'url' => $reportedContentUrl,
            ],

            'parent_content' => $parentContent,

            'reporter' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                // PERBAIKAN KUNCI: Cek jika route ada sebelum digunakan, jika tidak, gunakan '#'
                'url' => Route::has('users.profile.show') ? route('users.profile.show', ['user' => $this->user->id]) : '#',
            ],
        ];
    }
}
