<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\User;
use Illuminate\Http\Request;

class QuestionController extends BaseController
{
    protected $userController;
    public function __construct(Question $model)
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

    public function getQuestionByAnswerId($answer_id)
    {
        $question = $this->model::with($this->model->relatons())->findOrFail($answer_id);
        return $this->success('Successfully retrieved data', $question);
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
