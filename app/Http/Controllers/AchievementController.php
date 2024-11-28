<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use Illuminate\Http\Request;

class AchievementController extends BaseController
{
    public function __construct(Achievement $model)
    {
        parent::__construct($model);
    }
}
