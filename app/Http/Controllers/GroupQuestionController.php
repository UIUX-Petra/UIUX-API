<?php

namespace App\Http\Controllers;

use App\Utils\HttpResponse;
use App\Utils\HttpResponseCode;
use Illuminate\Http\Request;
use App\Models\GroupQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Validator;

class GroupQuestionController extends BaseController
{
    public function __construct(GroupQuestion $model)
    {
        parent::__construct($model);
    }
    public function store(Request $request)
    {
        $questionId = $request->input('question_id');
        $tags = $request->input('tags', []);

        if (empty($questionId) || empty($tags)) {
            return $this->error('Question ID and at least one tag are required.');
        }

        try {
            DB::transaction(function () use ($questionId, $tags) {
                $this->model->where('question_id', $questionId)->delete();

                foreach ($tags as $tag) {
                    $this->model->create([
                        'question_id' => $questionId,
                        'tag_id' => $tag['id'],
                        'is_recommended' => $tag['is_recommended']
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error("Failed to store subject questions: " . $e->getMessage());
            return $this->error('An error occurred while saving tags.');
        }

        return $this->success('Tags successfully linked to question.');
    }
}
