<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Question;

class ProcessQuestionAiServices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $questionId;
    protected $tagsData;
    protected $imagePath;

    public function __construct(string $questionId, array $tagsData, ?string $imagePath)
    {
        $this->questionId = $questionId;
        $this->tagsData = $tagsData;
        $this->imagePath = $imagePath;
    }

    public function handle()
    {
        $question = Question::find($this->questionId);
        if (!$question) {
            return;
        }

        $aiServiceUrl = env('AI_SERVICE_URL', 'http://localhost:5000/ai');

        try {
            $feedbackRequest = Http::asMultipart();
            if ($this->imagePath && file_exists(storage_path('app/public/' . $this->imagePath))) {
                $feedbackRequest->attach(
                    'image',
                    file_get_contents(storage_path('app/public/' . $this->imagePath)),
                    basename($this->imagePath)
                );
            }

            $payload = [
                ['name' => 'title', 'contents' => $question->title],
                ['name' => 'question', 'contents' => $question->question],
            ];
            foreach ($this->tagsData['selected_tags'] as $tag) {
                $payload[] = ['name' => 'selected_tags[]', 'contents' => $tag];
            }
            foreach ($this->tagsData['recommended_tags'] as $tag) {
                $payload[] = ['name' => 'recommended_tags[]', 'contents' => $tag];
            }

            $feedbackRequest->post("$aiServiceUrl/tag_feedback", $payload);

            Http::post("$aiServiceUrl/process_embeddings", [
                'question_id' => $question->id
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('AI Service connection failed for question ' . $this->questionId . ': ' . $e->getMessage());
            $this->release(600);
        } catch (\Throwable $e) {
            Log::error('An unexpected error occurred in ProcessQuestionAiServices job for question ' . $this->questionId . ': ' . $e->getMessage());
        }
    }
}