<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\User;
use Illuminate\Http\Request;

class AnswerController extends BaseController
{
    protected $userController;
    public function __construct(Answer $model)
    {
        parent::__construct($model);
        $this->userController = new UserController(new User());
    }

    public function store(Request $request){
        $userId = $this->userController->getUserId($request->email);
        $request->merge(['user_id' => $userId]);
        $request->request->remove('email');
        return parent::store($request);
    }

    public function getAnswerByQuestionId($question_id)
    {
        $answers = $this->model::with($this->model->relations())->where('question_id', $question_id)->get();
        return $this->success('Successfully retrieved data', $answers);
    }

    public function upvoteAnswer(Request $request, $id)
    {
        $answer = $this->model::findOrFail($id);
        $userId = $this->userController->getUserId($request->email);
        if (is_null($userId)) {
            return $this->error('User not found with the provided email.');
        }
        try {
            $answer->upvote($userId);
            return $this->success('Answer upvoted successfully.', $answer->vote);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function downvoteAnswer(Request $request, $id)
    {
        $answer = $this->model::findOrFail($id);
        $userId = $this->userController->getUserId($request->email);
        if (is_null($userId)) {
            return $this->error('User not found with the provided email.');
        }
        try {
            $answer->downvote($userId);
            return $this->success('Answer downvoted successfully.', $answer->vote);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

}
