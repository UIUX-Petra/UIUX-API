<?php

namespace App\Http\Controllers;

use Http;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Validator;

class UserController extends BaseController
{
    public function __construct(User $user)
    {
        parent::__construct($user);
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
        // Log the incoming email
        Log::info('Searching for user by email', ['email' => $email]);

        $userDiCari = $this->model::where('email', $email)->with($this->model->relations())->get()->first();

        // Log if the user was found or not
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
                        $recommendedUserIds = array_column($recommendations, 0);
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
                ? collect($recommendations)->firstWhere(0, $user->id)[1]
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

        return $this->success('Successfully retrieved data', [
            'user' => $currUser->load(['following', 'followers']),
            'countFollowers' => count($mauDiFolo['followers'])
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
}
