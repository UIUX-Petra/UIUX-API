<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use Illuminate\Http\Request;

class AnswerController extends BaseController
{
    public function __construct(Answer $model)
    {
        parent::__construct($model);        
    }
}
