<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessQuestionAiServices;
use App\Models\User;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\GroupQuestion;
use App\Utils\HttpResponseCode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\GroupQuestionController;

class QuestionController extends BaseController
{
    protected $userController, $tagsQuestionController;
    public function __construct(Question $model)
    {
        parent::__construct($model);
        $this->userController = new UserController(new User());
        $this->tagsQuestionController = new GroupQuestionController(new GroupQuestion());
    }

    public function getUserQuestionsWithCount($email)
    {
        $questions = $this->model->where('email', $email)
            ->orderBy('created_at', 'desc')
            ->get();

        // $count = $questions->count();

        return $this->success('Successfully retrieved data', $questions);
    }


    public function getQuestionPaginated(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $userEmail = $request->input('email');

        $sortBy = $request->input('sort_by', 'latest');
        $filterTag = $request->input('filter_tag', null);

        $requestUser = null;
        if ($userEmail) {
            $requestUser = User::where('email', $userEmail)->first();
        }

        $query = $this->model->with($this->model->getDefaultRelations()) // Asumsi getDefaultRelations() ada
            ->withCount([
                'comment as comments_count',
            ]);

        // filter berdasarkan Tag
        if ($filterTag) {
            $query->whereHas('groupQuestion.subject', function ($q) use ($filterTag) {
                $q->where('name', 'like', '%' . $filterTag . '%');
            });
        }

        switch ($sortBy) {
            case 'views':
                $query->orderBy('view', 'desc');
                break;
            case 'votes':
                $query->orderBy('vote', 'desc');
                break;
            case 'latest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }
        if ($sortBy === 'views' || $sortBy === 'votes') {
            $query->orderBy('created_at', 'desc');
        }

        if ($requestUser) {
            $query->withExists([
                'savedByUsers as is_saved_by_request_user' => function ($subQuery) use ($requestUser) {
                    $subQuery->where('saved_questions.user_id', $requestUser->id);
                }
            ]);
        }

        $data = $query->paginate($perPage);

        if ($data->isNotEmpty()) {
            $data->getCollection()->transform(function ($item) {
                $item->is_saved_by_request_user = (bool) ($item->is_saved_by_request_user ?? false);

                if (isset($item->view_count)) {
                    $item->view = (int) $item->view_count;
                }
                if (isset($item->vote_count)) {
                    $item->vote = (int) $item->vote_count;
                }
                if (isset($item->comments_count)) {
                    $item->comments_count = (int) $item->comments_count;
                }

                return $item;
            });
        }

        return $this->success('Successfully retrieved data', $data);
    }

    // public function getQuestionPaginatedHome(Request $request)
    // {
    //     $perPage = $request->input('per_page', 10);
    //     $userEmail = $request->input('email');
    //     // $filterTag = $request->input('filter_tag', null); // Tetap ada filter berdasarkan tag

    //     $requestUser = null;
    //     if ($userEmail) {
    //         $requestUser = User::where('email', $userEmail)->first();
    //     }

    //     $query = $this->model->with($this->model->getDefaultRelations()) // Asumsi getDefaultRelations() ada
    //         ->withCount([
    //             'comment as comments_count',
    //         ]);

    //     $query->orderBy('created_at', 'desc'); // Default order by terbaru

    //     if ($requestUser) {
    //         $query->withExists([
    //             'savedByUsers as is_saved_by_request_user' => function ($subQuery) use ($requestUser) {
    //                 $subQuery->where('saved_questions.user_id', $requestUser->id);
    //             }
    //         ]);
    //     }

    //     $data = $query->paginate($perPage);

    //     if ($data->isNotEmpty()) {
    //         $data->getCollection()->transform(function ($item) {
    //             $item->is_saved_by_request_user = (bool) ($item->is_saved_by_request_user ?? false);
    //             return $item;
    //         });
    //     }

    //     return $this->success('Successfully retrieved data', $data);
    // }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'question' => 'required|string',
            'image' => 'nullable|file|image|max:2048',
            'selected_tags' => 'required|array|min:1',
            'selected_tags.*' => 'string|uuid',
            'recommended_tags' => 'nullable|array',
            'recommended_tags.*' => 'string|uuid',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        

        DB::beginTransaction();
        try {
            $validatedData = $validator->validated();
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                $user = $request->user();
                $timestamp = date('Y-m-d_H-i-s');
                $extension = $imageFile->getClientOriginalExtension();
                $customFileName = "q_" . ($user->email ?? 'user' . $user->id) . "_" . $timestamp . "." . $extension;

                $imagePath = $imageFile->storeAs("question_images", $customFileName, 'public');
            }

            $question = $this->model->create([
                'title' => $validatedData['title'],
                'question' => $validatedData['question'],
                'image' => $imagePath,
                'user_id' => $request->user()->id,
            ]);

            $selectedTags = $validatedData['selected_tags'];
            $recommendedTags = $validatedData['recommended_tags'] ?? [];
            $tagsForController = [];
            foreach ($selectedTags as $tagId) {
                $tagsForController[] = ['id' => $tagId, 'is_recommended' => in_array($tagId, $recommendedTags)];
            }
            $tagsRequest = new Request(['question_id' => $question->id, 'tags' => $tagsForController]);
            $this->tagsQuestionController->store($tagsRequest);

            $tagsData = [
                'selected_tags' => $request->input('selected_tags', []),
                'recommended_tags' => $request->input('recommended_tags', [])
            ];
            ProcessQuestionAiServices::dispatch($question->id, $tagsData, $imagePath);

            DB::commit();

            return $this->success('Question published successfully!', $question);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API Store Question Failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            return $this->error('Server error while saving question.', 500);
        }
    }

    public function updatePartial(Request $request, $id)
    {
        $question = $this->model->findOrFail($id);

        if ($request->user()->id !== $question->user_id) {
            return $this->error('You are not authorized to edit this question.', 403);
        }
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'question' => 'sometimes|required|string',
            'image' => 'nullable|file|image|max:2048',
            'remove_existing_image' => 'nullable|in:1',
            'selected_tags' => 'sometimes|required|array|min:1',
            'selected_tags.*' => 'string|uuid',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        DB::beginTransaction();
        try {
            $validatedData = $validator->validated();
            $question->update($request->only('title', 'question'));
            $oldImage = $question->image;
            $newImagePath = $oldImage;

            if ($request->hasFile('image')) {
                if ($oldImage && Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
                $newImagePath = $request->file('image')->store('question_images', 'public');

            } elseif ($request->input('remove_existing_image') == '1' && $oldImage) {
                if (Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
                $newImagePath = null;
            }
            $question->image = $newImagePath;
            $question->save();
            if ($request->has('selected_tags')) {
                $tagsForController = [];
                foreach ($validatedData['selected_tags'] as $tagId) {
                    $tagsForController[] = ['id' => $tagId, 'is_recommended' => false];
                }
                $tagsRequest = new Request(['question_id' => $question->id, 'tags' => $tagsForController]);
                $this->tagsQuestionController->store($tagsRequest);
            }
            Http::async()->post(env('AI_SERVICE_URL', 'http://localhost:5000/ai') . "/process_embeddings", [
                'question_id' => $question->id
            ]);
            DB::commit();
            return $this->success('Question updated successfully!', $question);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('API Update Question Failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            return $this->error('Server error while updating question.', 500);
        }
    }

    public function getQuestionByAnswerId($answer_id)
    {
        $question = $this->model::with($this->model->relations())->findOrFail($answer_id);
        return $this->success('Successfully retrieved data', $question);
    }

    public function viewQuestion(Request $request, $id) //ini emang ga butuh login sih, tapi pas fetch comment, masih harus login
    {
        $question = $this->model::with(array_merge($this->model->relations(), ['comment.user', 'answer.user', 'answer.comment.user']))->findOrFail($id);
        $userId = $this->userController->getUserId($request->email);
        if (!$question) {
            return $this->error('Question not found with the provided id.');
        }
        if (is_null($userId)) {
            return $this->error('User not found with the provided email.');
        }
        $answers = $question->answer->map(function ($answer) {
            return [
                'id' => $answer->id,
                'username' => $answer->user->username,
                'email' => $answer->user->email,
                'user_image' => $answer->user->image,
                'image' => $answer->image,
                'answer' => $answer->answer,
                'vote' => $answer->vote,
                'verified' => $answer->verified,
                'timestamp' => $answer->created_at,
                'comments' => $answer->comment->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'username' => $comment->user->username,
                        'user_email' => $comment->user->email,
                        'comment' => $comment->comment,
                        'timestamp' => $comment->created_at
                    ];
                }),
            ];
        });
        $comment = $question->comment->map(function ($comment) {
            return [
                'id' => $comment->id,
                'username' => $comment->user->username,
                'email' => $comment->user->email,
                'comment' => $comment->comment,
                'timestamp' => $comment->created_at,
            ];
        });

        $question->timestamp = $question->created_at;
        $question->setRelation('answer', $answers);
        $question->setRelation('comment', $comment);
        try {
            $question->view($userId);
            return $this->success('Question viewed successfully.', $question);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function upvoteQuestion(Request $request, $id)
    {
        $question = $this->model::findOrFail($id);
        $userId = $this->userController->getUserId($request->email);
        if (is_null($userId)) {
            return $this->error('User not found or not authenticated.');
        }
        try {
            $question->upvote($userId);
            return $this->success('Question upvoted successfully.', $question->vote);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error("Error upvoting question {$id} by user {$userId}: " . $e->getMessage(), ['exception' => $e]);
            return $this->error('An error occurred while processing your vote.');
        }
    }

    public function downvoteQuestion(Request $request, $id)
    {
        $question = $this->model::findOrFail($id);
        $userId = $this->userController->getUserId($request->email);
        if (is_null($userId)) {
            return $this->error('User not found or not authenticated.');
        }
        try {
            $question->downvote($userId);
            return $this->success('Question downvoted successfully.', $question->vote);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error("Error downvoting question {$id} by user {$userId}: " . $e->getMessage(), ['exception' => $e]);
            return $this->error('An error occurred while processing your vote.');
        }
    }
}
