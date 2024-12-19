<?php

namespace App\Http\Controllers;

use App\Utils\HttpResponse;
use App\Utils\HttpResponseCode;
use Illuminate\Http\Request;
use App\Models\GroupQuestion;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Validator;

class GroupQuestionController extends BaseController
{
    public function __construct(GroupQuestion $model)
    {
        parent::__construct($model);
    }

    public function store(Request $request)
    { //multi-store
        $data = $request->only($this->model->getFillable());
        Log::info($request);
        $rel['question_id'] = $data['question_id'];

        foreach ($data['tag_id'] as $subsID){
            $rel['tag_id'] = $subsID;
            $this->model->create($rel);
        };
        return $this->success('Data successfully saved to model', $this->model);
    }
}
