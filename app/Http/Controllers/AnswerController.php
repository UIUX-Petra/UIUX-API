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

    public function getAnswerByQuestionId($question_id){ //Ambil semua jawaban dari suatu soal
        $answers = Answer::with($this->model->relations())->where('question_id', $question_id)->get();
        return $this->success('Successfully retrieved data',$answers);
    }
}
