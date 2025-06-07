<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Subject;
use App\Models\Question;
use Illuminate\Http\Request;
use App\Models\GroupQuestion;
use App\Utils\HttpResponseCode;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\UserController;
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

    public function getQuestionPaginatedHome(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $userEmail = $request->input('email');
        // $filterTag = $request->input('filter_tag', null); // Tetap ada filter berdasarkan tag

        $requestUser = null;
        if ($userEmail) {
            $requestUser = User::where('email', $userEmail)->first();
        }

        $query = $this->model->with($this->model->getDefaultRelations()) // Asumsi getDefaultRelations() ada
            ->withCount([
                'comment as comments_count',
            ]);

        $query->orderBy('created_at', 'desc'); // Default order by terbaru

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
                return $item;
            });
        }

        return $this->success('Successfully retrieved data', $data);
    }


    public function store(Request $request)
    {
        $userId = $this->userController->getUserId($request->email);
        $request->merge(['user_id' => $userId]);
        $request->request->remove('email');

        $data = $request->only($this->model->getFillable());
        $valid = Validator::make($data, $this->model->validationRules(), $this->model->validationMessages());

        if ($valid->fails()) {
            $validationError = $valid->errors()->first();
            Log::error("Validation failed:", ['error' => $validationError]);
            return $this->error($validationError, HttpResponseCode::HTTP_NOT_ACCEPTABLE);
        }

        $model = $this->model->create($data);
        $request->request->add(['question_id' => $model->id]);
        $this->tagsQuestionController->store($request);
        return $this->success('Data successfully saved to model', $model);
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
