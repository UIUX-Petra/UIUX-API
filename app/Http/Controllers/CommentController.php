<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends BaseController
{
    public function __construct(Comment $model)
    {
        parent::__construct($model);
    }
    
    public function store(Request $request){

    }
}
