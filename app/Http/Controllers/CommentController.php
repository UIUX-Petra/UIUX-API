<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use App\Models\Comment;
use App\Utils\HttpResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Validator;

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
        $validator = Validator::make(
            $request->all(),
            $this->model->validationRules() + ['email' => 'required|email|exists:users,email'],
            $this->model->validationMessages() + ['email.required' => 'The user email is required.']
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_NOT_ACCEPTABLE);
        }

        $commentableType = $request->input('commentable_type');
        $commentableId = $request->input('commentable_id');

        $modelMap = [
            'question' => Question::class,
            'answer' => Answer::class,
        ];

        $parentModelClass = $modelMap[$commentableType] ?? null;
        if (!$parentModelClass) {
            return $this->error('Invalid commentable_type provided.', HttpResponseCode::HTTP_BAD_REQUEST);
        }

        $parent = $parentModelClass::find($commentableId);

        if (!$parent) {
            return $this->error(ucfirst($commentableType) . ' not found.', HttpResponseCode::HTTP_NOT_FOUND);
        }
        $userId = $this->userController->getUserId($request->email);
        $comment = $parent->comment()->create([
            'comment' => $request->input('comment'),
            'user_id' => $userId,
        ]);
        $comment->load('user');
        $comment->makeVisible(['created_at']);
        $responseData = [
            'data' => [
                'comment' => $comment
            ],
            'code' => HttpResponseCode::HTTP_OK,
            'message' => 'Data successfully saved to model',
        ];

        return response()->json($responseData, $responseData['code']);
    }
}
