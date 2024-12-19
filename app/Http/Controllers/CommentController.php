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

    public function store(Request $request){
        $userId = $this->userController->getUserId($request->email);
        $request->merge(['user_id' => $userId]);
        $request->request->remove('email');
        return parent::store($request);
    }
}
