<?php

namespace App\Http\Controllers;

use Http;
use App\Models\User;
use App\Models\Question;
use App\Utils\HttpResponseCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Validator;

class UserController extends BaseController
{
    // protected $questionController;
    public function __construct(User $model)
    {
        parent::__construct($model);
        // $this->questionController = new QuestionController(new Question());
    }
    public function firstOrCreate($data)
    {
        return $this->model::firstOrCreate(
            ['email' => $data['email']],
            [
                'username' => $data['name'],
            ]
        );
    }

    public function index()
    {
        $data = $this->model->with($this->model->relations())->get();
        $data = $data->makeVisible(['created_at']);
        return $this->success('Successfully retrieved data', $data);
    }

    public function getUserId($email)
    {
        $user = $this->model::where('email', $email)->first();
        if (!$user) {
            return null;
        }
        return $user->id;
    }

    public function create($data)
    {
        return $this->model::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }

    public function getByEmail(string $email)
    {
        Log::info('Searching for user by email', ['email' => $email]);

        $userDiCari = $this->model::where('email', $email)
            ->with(array_merge(
                $this->model->relations(),
                [
                    'histories.searched' => function ($morphTo) {
                        $morphTo->morphWith([
                            \App\Models\Question::class => ['user:id,username'],
                            \App\Models\User::class => [],
                            \App\Models\Subject::class => [],
                        ]);
                    }
                ]
            ))
            ->first();

        if ($userDiCari) {
            Log::info('User found', ['user_id' => $userDiCari->id, 'email' => $email]);
        } else {
            Log::warning('User not found', ['email' => $email]);
        }

        return $this->success('Successfully retrieved data', $userDiCari);
    }


    public function getUserWithRecommendation(Request $request)
    {
        $recommendations = [];
        $recommendedUserIds = [];

        if ($request->has('email')) {
            $user = $this->model::where('email', $request->email)->first();
            if ($user) {
                try {
                    $response = Http::get(env('PYTHON_API_URL') . '/recommend', [
                        'user' => $user->id,
                    ]);
                    if ($response->successful()) {
                        $recommendations = $response->json()['data'] ?? [];
                        $recommendedUserIds = collect($recommendations)->pluck('user_id')->toArray();
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to connect to recommendation API: ' . $e->getMessage());
                }
            } else {
                Log::info('Invalid email provided: ' . $request->email);
            }
        }
        $users = $this->model->with($this->model->relations())->get();
        $users = $users->makeVisible(['created_at']);
        $users = $this->model::orderBy('reputation', 'desc')->get();
        $result = $users->map(function ($user) use ($recommendations, $recommendedUserIds) {
            $isRecommended = in_array($user->id, $recommendedUserIds);
            $score = $isRecommended
                ? collect($recommendations)->firstWhere('user_id', $user->id)['score']
                : null;
            return [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'image' => $user->image,
                'biodata' => $user->biodata,
                'reputation' => $user->reputation,
                'is_recommended' => $isRecommended,
                'score' => $score,
                'question' => $user->question,
                'created_at' => $user->created_at,
            ];
        });
        $sortedResult = $result->sortByDesc(function ($user) {
            return [$user['is_recommended'], $user['reputation']];
        })->values();
        return $this->success('Data retrieved successfully.', $sortedResult);
    }


    public function follow(string $email, Request $reqs)
    {
        $currUser = $this->model::where('email', $reqs->emailCurr)->get()->first();
        $mauDiFolo = $this->model::where('email', $email)->get()->first();

        if (!$currUser || !$mauDiFolo) {
            return $this->error('Missing User or Target');
        }

        if ($currUser['id'] == $mauDiFolo['id']) {
            return $this->error('You Cannot Follow Yourself');
        }

        if ($mauDiFolo) {

            if (($currUser->following()->where('followed_id', $mauDiFolo->id))->exists()) { //unFoll
                $currUser->following()->detach($mauDiFolo->id);
            } else {
                $currUser->following()->attach($mauDiFolo->id, ['id' => Str::uuid()]); //Folo
            }
        }
        $mauDiFolo = $mauDiFolo->load(['following', 'followers']);

        $userRelation = 0; // tak ada relasi (asing bjir)
        foreach ($mauDiFolo['followers'] as $follower) {
            if ($follower['id'] == $currUser['id']) {
                $userRelation = 1; // aku follow dirinya -> btn bertuliskan following
                break;
            }
        }

        foreach ($mauDiFolo['following'] as $following) {
            if ($following['id'] == $currUser['id']) {
                if ($userRelation == 0) { // jika habis di cek, trnyt ak ga folo dia, tapi dia folo ak -> btn bertuliskan follow back
                    $userRelation = 2;
                } else if ($userRelation == 1) { // jika habis di cek, trnyata aku follow dia, cek apakah kita mutual (dia folback)
                    $userRelation = 3;
                }
                break;
            }
        }
        $currUser = $currUser->load('following');
        $myFollow = count($currUser['following']);

        return $this->success('Successfully retrieved data', [
            'userRelation' => $userRelation,
            'countFollowers' => count($mauDiFolo['followers']),
            'targetEmail' => $mauDiFolo->email,
            'myFollow' => $myFollow,
            'targetUsername' => $mauDiFolo->username,
        ]);
    }

    public function getFollowing(string $id)
    {
        $user = $this->model::find($id);
        $followings = $user->following;
        return $this->success('Successfully retrieved data', $followings);
    }

    public function getFollower(string $id)
    {
        $user = $this->model::find($id);
        $followersUser = $user->followers;
        return $this->success('Successfully retrieved data', $followersUser);
    }
    public function getUserQuestions(string $id)
    {
        return $this->success('Successfully retrieved data', $this->model->with(['question.answer'])->findOrFail($id));
    }

    public function editProfileUser(Request $request)
    {
        Log::info($request);
        $user = $this->model::find($request->user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($request->has('username') && $user->username !== $request->username) {
            $user->username = $request->username;
        }

        if ($request->has('image')) {
            $user->image = $request->image;
        }

        if ($request->has('biodata')) {
            $user->biodata = $request->biodata;
        }

        $user->save();

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user], 200);
    }

    // buat hitung satu user punya berapa post dengan suatu tag
    public function getUserTags(Request $request)
    {
        Log::info('Fetching user tags for email: ' . $request->email);
        try {
            $currUser = $this->model::where('email', $request->email)
                ->with([
                    'question.groupQuestion.subject',
                ])
                ->first();

            if ($currUser) {
                Log::info('Successfully retrieved user data for email: ' . $request->email);
                return $this->success('Successfully retrieved data', $currUser);
            } else {
                Log::warning('User not found for email: ' . $request->email);
                return $this->error('User not found');
            }
        } catch (\Exception $e) {
            Log::error('Error fetching user data for email: ' . $request->email . ' - ' . $e->getMessage());
            return $this->error('An error occurred while processing your request.');
        }
    }


    public function getLeaderboardByTag($tagId)
    {
        // $data = json_encode($request->all());

        try {
            // $validator = Validator::make($data, [
            //     'tag_id' => 'required|string',
            //     'top_n' => 'nullable|integer|min:1'
            // ], [
            //     'tag_id.required' => 'The tag_id field is required.',
            //     'tag_id.string' => 'The tag_id must be a string.',
            //     'top_n.integer' => 'The top_n must be a valid integer.',
            //     'top_n.min' => 'The top_n must be at least 1.',
            // ]);

            // if ($validator->fails()) {
            //     return $this->error('Invalid request data.', HttpResponseCode::HTTP_BAD_REQUEST, $validator->errors()->first());
            // }

            // $validatedData = $validator->validated();

            // $queryParams = ['tag' => $validatedData['tag_id']];
            // if (isset($validatedData['top_n'])) {
            $queryParams = ['tag' => $tagId];
            // $queryParams['tag_id'] = 
            $queryParams['top_n'] = 3;
            // }

            $response = Http::get(env('PYTHON_API_URL') . '/leaderboard', $queryParams);

            if (!$response->successful()) {
                return $this->error('Failed to fetch leaderboard data', [], 500);
            }

            $leaderboardData = $response->json()['data'] ?? [];
            $userIds = collect($leaderboardData)->pluck('user_id');
            $users = $this->model::whereIn('id', $userIds)->get()->keyBy('id');

            $leaderboard = collect($leaderboardData)->map(function ($entry) use ($users) {
                $user = $users->get($entry['user_id']);

                return [
                    'user_id' => $entry['user_id'],
                    'contributions' => $entry['contributions'],
                    'username' => $user?->username ?? 'Unknown User',
                    'email' => $user?->email ?? 'Unknown Email',
                ];
            });

            return $this->success('Successfully retrieved data', $leaderboard);
        } catch (\Exception $e) {
            Log::error('Failed to fetch leaderboard data: ' . $e->getMessage());
            return $this->error('An error occurred while retrieving the leaderboard data.', [], 500);
        }
    }
    public function getMostViewed($email)
    {
        $id = $this->getUserId($email);
        $queryParams = ['user' => $id, 'top_n' => 1];

        try {
            $response = Http::get(env('PYTHON_API_URL') . '/top-viewed', $queryParams);
            if ($response->successful()) {
                $mostViewedData = $response->json()['data'][0] ?? null;
                if ($mostViewedData && isset($mostViewedData['owner_user_id'])) {
                    $mostViewed = $this->model::find($mostViewedData['owner_user_id']);
                    if ($mostViewed) {
                        $result = [
                            'username' => $mostViewed->username,
                            'email' => $mostViewed->email,
                            'image' => $mostViewed->image,
                        ];
                        return $this->success('Successfully retrieved top viewed data', $result);
                    } else {
                        return $this->error('User associated with the top-viewed data was not found.');
                    }
                }
                return $this->error('No top-viewed data found for the user.');
            } else {
                return $this->error('Failed to retrieve top viewed data from the external service.');
            }
        } catch (\Exception $e) {
            return $this->error('An error occurred while retrieving top viewed data: ' . $e->getMessage());
        }
    }

    public function saveQuestion($email, $questionId)
    {
        $user = $this->model::where('email', $email)->get()->first();
        $question = Question::findOrFail($questionId);

        if (!$user || !$question) {
            return $this->error('User or question not found', [], HttpResponseCode::HTTP_NOT_FOUND);
        }

        if ($user->savedQuestions()->where('question_id', $questionId)->exists()) {
            return $this->error('Question already saved', [], HttpResponseCode::HTTP_BAD_REQUEST);
        }

        $user->savedQuestions()->attach($questionId);

        return $this->success('Question saved successfully', [], HttpResponseCode::HTTP_OK);
    }

    public function unsaveQuestion($email, $questionId)
    {
        $user = $this->model::where('email', $email)->get()->first();
        $question = Question::findOrFail($questionId);
        if (!$user || !$question) {
            return $this->error('User or question not found', [], HttpResponseCode::HTTP_NOT_FOUND);
        }

        if (!$user->savedQuestions()->where('question_id', $questionId)->exists()) {
            return $this->error('Question not saved yet', [], HttpResponseCode::HTTP_BAD_REQUEST);
        }

        $user->savedQuestions()->detach($questionId);

        return $this->success('Question unsaved successfully', [], HttpResponseCode::HTTP_OK);
    }

    public function getSavedQuestions($email)
    {
        $user = $this->model::where('email', $email)->get()->first();

        if (!$user) {
            return $this->error('User not found', [], HttpResponseCode::HTTP_NOT_FOUND);
        }

        $savedQuestions = $user->savedQuestions()
            ->with('groupQuestion.subject')
            ->withCount('comment')
            ->get();
        Log::info("messageTES" . $savedQuestions);
        return $this->success('Successfully retrieved saved questions', $savedQuestions, HttpResponseCode::HTTP_OK);
    }
}
