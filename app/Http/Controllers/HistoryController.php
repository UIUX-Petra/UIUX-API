<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Answer;
use App\Models\History;
use App\Models\Question;
use Illuminate\Http\Request;

class HistoryController extends BaseController
{

    protected $userController;
    public function __construct(History $model)
    {
        parent::__construct($model);
        $this->userController = new UserController(new User());
    }

    public function store(Request $request)
    {
        $userId = $this->userController->getUserId($request->email);
        $request->merge(['user_id' => $userId]);
        $request->request->remove('email');

        return parent::store();
    }
}
