<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends BaseController
{
    public function __construct(Subject $model)
    {
        parent::__construct($model);
    }
}
