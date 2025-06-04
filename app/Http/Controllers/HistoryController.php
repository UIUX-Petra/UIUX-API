<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Answer;
use App\Models\History;
use App\Models\Subject;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        return parent::store($request);
    }

    public function getDetailedSearchHistory(Request $request)
    {
        $userId = $this->userController->getUserId($request->email);

        $histories = History::where('user_id', $userId)->get();

        $result = $histories->map(function ($history) {
            switch ($history->searched_type) {
                case 'user':
                    $user = User::find($history->searched_id);
                    if (!$user) return null;

                    return [
                        'id' => $user->id,
                        'type' => 'user',
                        'username' => $user->username,
                        'email' => $user->email,
                        'image' => $user->image,
                        'url' => '/viewUser/' . $user->email,
                    ];

                case 'question':
                    $question = Question::with('groupQuestion.subject')->find($history->searched_id);
                    if (!$question) return null;

                    $subjectNames = $question->groupQuestion
                        ->pluck('subject.name')
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();

                    return [
                        'id' => $question->id,
                        'type' => 'question',
                        'title' => $question->title,
                        'author_username' => $question->user->username ?? 'Unknown',
                        'subject_name' => $subjectNames[0] ?? null,
                        'subject_names' => $subjectNames,
                        'url' => '/viewAnswers/' . $question->id,
                    ];

                case 'subject':
                    $subject = Subject::find($history->searched_id);
                    if (!$subject) return null;

                    return [
                        'id' => $subject->id,
                        'type' => 'subject',
                        'name' => $subject->name,
                        'url' => '/popular?sort_by=latest&filter_tag=' . urlencode($subject->name),
                    ];

                default:
                    return null;
            }
        })->filter()->values();

        return response()->json(['data' => $result]);
    }
    public function clearHistory($email)
    {
        $userResponse = $this->userController->getByEmail($email);
        $user = $userResponse->getData(true);

        if (!$user['success'] && !$user['data']) {
            return $this->error("Failed to fetch User's Histories", 500, ['email' => $email]);
        }
        History::where('user_id', $user['data']['id'])->delete();
        return $this->success("History Cleared", null, 200);
    }
}
