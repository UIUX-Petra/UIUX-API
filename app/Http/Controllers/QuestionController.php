<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\User;
use App\Utils\HttpResponseCode;
use Illuminate\Http\Request;

class QuestionController extends BaseController
{
    protected $userController;
    public function __construct(Question $model)
    {
        parent::__construct($model);
        $this->userController = new UserController(new User());
    }

    public function store(Request $request)
    {
        $userId = $this->userController->getUserId($request->email);
        $request->merge(['user_id' => $userId]);
        $request->request->remove('email');
        return parent::store($request);
    }

    public function getQuestionByAnswerId($answer_id)
    {
        $question = $this->model::with($this->model->relatons())->findOrFail($answer_id);
        return $this->success('Successfully retrieved data', $question);
    }

    public function viewQuestion(Request $request, $id)
    {
        $question = $this->model::with(array_merge($this->model->relations(), ['answer.user', 'answer.comment.user']))->findOrFail($id);
        $userId = $this->userController->getUserId($request->email);
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
                        'comment' => $comment->comment,
                    ];
                }),
            ];
        });
        $question->setRelation('answer', $answers);
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
