<?php

namespace App\Http\Controllers;

use App\Models\UserAchievement;
use Illuminate\Http\Request;

class UserAchievementController extends BaseController
{
    public function __construct(UserAchievement $model)
    {
        parent::__construct($model);
    }
}
