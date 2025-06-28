<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Asumsikan controller sudah memuat relasi ini dengan withTrashed()
        // Contoh di controller: Report::with('user', 'reason', 'reportable')->...
        $reportable = $this->resource->reportable;

        // Inisialisasi variabel default
        $parentContent = null;
        $reportedContentUrl = '#';
        // Pesan default jika konten dihapus permanen (hard-deleted)
        $contentPreview = 'Konten sumber tidak ditemukan (dihapus permanen).'; 

        // Proses jika relasi reportable ditemukan (termasuk yang sudah di-soft-delete)
        if ($reportable) {
            $type = $reportable->getMorphClass();

            // Selalu bangun URL dan parent content, terlepas dari status soft-delete
            switch ($type) {
                case 'question':
                    if (Route::has('questions.show')) {
                        $reportedContentUrl = route('questions.show', $reportable->id);
                    }
                    break;

                case 'answer':
                    // Gunakan withTrashed() juga untuk relasi question di model Answer jika perlu
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
                     // Gunakan withTrashed() juga untuk relasi commentable di model Comment jika perlu
                    if ($reportable->commentable) {
                        $parent = $reportable->commentable;
                        $parentType = $parent->getMorphClass();

                        if ($parentType === 'question' && Route::has('questions.show')) {
                            $questionUrl = route('questions.show', $parent->id);
                            $reportedContentUrl = $questionUrl . '#comment-' . $reportable->id;
                            $parentContent = [
                                'type' => 'Question',
                                'id' => $parent->id,
                                'title' => $parent->title,
                                'url' => $questionUrl,
                            ];
                        } elseif ($parentType === 'answer' && $parent->question && Route::has('questions.show')) {
                            $questionUrl = route('questions.show', $parent->question_id);
                            $reportedContentUrl = $questionUrl . '#comment-' . $reportable->id;
                            $parentContent = [
                                'type' => 'Answer',
                                'id' => $parent->id,
                                'title' => 'Jawaban untuk: ' . $parent->question->title,
                                'url' => $questionUrl . '#answer-' . $parent->id,
                            ];
                        }
                    }
                    break;
            }

            // Selalu buat pratinjau konten jika $reportable ada
            $content = '';
            if ($type === 'question') {
                $content = $reportable->title;
            } elseif ($type === 'answer') {
                $content = $reportable->answer;
            } elseif ($type === 'comment') {
                $content = $reportable->comment;
            }
            $contentPreview = Str::limit(strip_tags($content), 100);
        }

        // Mengembalikan array JSON yang terstruktur dan disederhanakan
        return [
            'id' => $this->id,
            'status' => $this->status,
            'reason' => $this->whenLoaded('reason', fn() => $this->reason->title, 'N/A'),
            'date_reported' => $this->created_at->format('d M Y, H:i'),
            'date_for_humans' => $this->created_at->diffForHumans(),

            'reported_content' => [
                'type' => $reportable ? Str::ucfirst($reportable->getMorphClass()) : 'Dihapus',
                'id' => $reportable ? $reportable->id : null,
                'preview' => $contentPreview,
                'url' => $reportedContentUrl,
            ],

            'parent_content' => $parentContent,

            'reporter' => [
                'id' => $this->user->id,
                'name' => $this->user->username,
                'url' => Route::has('users.profile.show') ? route('users.profile.show', ['user' => $this->user->id]) : '#',
            ],
            
            'date_processed' => $this->reviewed_at,

            'date_processed_for_humans' => $this->when($this->reviewed_at, function () {
                return Carbon::parse($this->reviewed_at)->diffForHumans();
            }),
        ];
    }
}
