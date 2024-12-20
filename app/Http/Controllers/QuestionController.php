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
        $request->request->add( ['question_id'=> $model->id]);
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
                'image' => $answer->image,
                'answer' => $answer->answer,
                'vote' => $answer->vote,
                'comments' => $answer->comment->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'username' => $comment->user->username,
                        'user_email' => $comment->user->email,
                        'comment' => $comment->comment,
                    ];
                }),
            ];
        });
        $comment = $question->comment->map(function ($comment) {
            return [
                'id' => $comment->id,
                'username' => $comment->user->username,
                'comment' => $comment->comment,
            ];
        });
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
            return $this->error('User not found with the provided email.');
        }
        try {
            $question->upvote($userId);
            return $this->success('Question upvoted successfully.', $question->vote);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }


    public function downvoteQuestion(Request $request, $id)
    {
        $question = $this->model::findOrFail($id);
        $userId = $this->userController->getUserId($request->email);
        if (is_null($userId)) {
            return $this->error('User not found with the provided email.');
        }
        try {
            $question->downvote($userId);
            return $this->success('Question downvoted successfully.', $question->vote);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

}
