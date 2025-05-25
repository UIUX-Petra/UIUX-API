<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\UserController;

class CommentController extends BaseController
{
    protected $userController;
    public function __construct(Comment $model)
    {
        parent::__construct($model);
        $this->userController = new UserController(new User());
    }

    public function store(Request $request)
    {
        $userId = $this->userController->getUserId($request->email);
        $request->merge(['user_id' => $userId]);
        $request->request->remove('email');

        $response = parent::store($request);
        $responseData = $response->getData(true);

        if (isset($responseData['data']['id'])) {
            $comment = $this->model::with(['user'])->find($responseData['data']['id']);
            $comment = $comment->makeVisible(['created_at']);
            $responseData['data']['comment'] = $comment;
            unset($responseData['data']['id']);
            unset($responseData['data']['question_id']);
            unset($responseData['data']['answer_id']);
            unset($responseData['data']['user_id']);

            return response()->json($responseData, $responseData['code']);
        }
        return $response;
    }
}
