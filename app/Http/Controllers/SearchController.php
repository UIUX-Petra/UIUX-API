<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Utils\HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SearchController extends Controller
{
    use HttpResponse;
    public function search(Request $request)
    {
        $query = $request->input('q');
        $context = $request->input('context', 'all');
        $limit = $request->input('limit', 5);

        $results = [
            'questions' => new Collection(),
            'subjects' => new Collection(),
            'users' => new Collection(),
        ];

        if (empty($query)) {
            return $this->success('', $results);
        }

        // Search Questions
        if ($context === 'all' || $context === 'questions') {
            $questionsQuery = Question::where('title', 'LIKE', "%{$query}%")
                ->orWhere('question', 'LIKE', "%{$query}%")
                ->with([
                    'user:id,username',
                    'groupQuestion.subject:id,name'
                ])
                ->select('id', 'title', 'user_id')
                ->take($limit);

            $foundQuestions = $questionsQuery->get();

            $results['questions'] = $foundQuestions->map(function ($item) {
                $mappedItem = new \stdClass();
                $mappedItem->id = $item->id;
                $mappedItem->title = $item->title;
                $mappedItem->type = 'question';
                $mappedItem->url = 'uiux/viewAnswers/' . $item->id;

                $mappedItem->author_username = null;
                if ($item->relationLoaded('user') && $item->user) {
                    $mappedItem->author_username = $item->user->username;
                }
                $mappedItem->subject_names = [];
                if ($item->relationLoaded('groupQuestion') && $item->groupQuestion && !$item->groupQuestion->isEmpty()) {
                    foreach ($item->groupQuestion as $group) {
                        if ($group && $group->relationLoaded('subject') && $group->subject) {
                            $mappedItem->subject_names[] = $group->subject->name;
                        }
                    }
                }
                $mappedItem->subject_name = !empty($mappedItem->subject_names) ? $mappedItem->subject_names[0] : null;
                return $mappedItem;
            });
        }

        // Search Subjects/Tags
        if ($context === 'all' || $context === 'subjects') {
            $results['subjects'] = Subject::where('name', 'LIKE', "%{$query}%")
                ->select('id', 'name')
                ->take($limit)
                ->get()
                ->map(function ($item) {
                    $item->type = 'subject';
                    $item->url = 'uiux/popular?sort_by=latest&filter_tag=' . urlencode($item->name);
                    return $item;
                });
        }

        // Search Users
        if ($context === 'all' || $context === 'users') {
            $results['users'] = User::where('username', 'LIKE', "%{$query}%")
                ->orWhere('username', 'LIKE', "%{$query}%")
                ->select('id', 'username', 'email', 'image')
                ->take($limit)
                ->get()
                ->map(function ($item) {
                    $item->type = 'user';
                    $item->url = 'uiux/viewUser/' . $item->email;
                    return $item;
                });
        }
        $filteredResults = array_filter($results, function (Collection $categoryResults) {
            return !$categoryResults->isEmpty();
        });

        return $this->success('', $filteredResults);
    }
}
