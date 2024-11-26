<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends BaseController
{
    public function __construct(Question $model)
    {
        parent::__construct($model);
    }
    
    public function getQuestionByAnswerId($answer_id){
        $question = Question::with($this->model->relatons())->findOrFail($answer_id);
        return $this->success('Successfully retrieved data',$question);
    }
}
