<?php

namespace App\Http\Controllers;

use App\Models\GroupQuestion;
use Illuminate\Http\Request;

class GroupQuestionController extends BaseController
{
    public function __construct(GroupQuestion $model)
    {
        parent::__construct($model);
    }
}
