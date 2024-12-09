<?php

namespace App\Http\Controllers;

use App\Utils\HttpResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class RecommendationController extends BaseController
{
    protected $userController;
    public function __construct(){
        $this->userController = new UserController(new User());
    }
    public function recommend(Request $request)
    {
        $userId = $this->userController->model::where('email', $request->email)->first('id');

        // Fetch recommendations from Python API
        $response = Http::get(env('PYTHON_API_URL') . '/recommend', [
            'user' => $userId->id,
        ]);

        if ($response->failed()) {
            return $this->error('Failed to fetch recommendations', HttpResponseCode::HTTP_BAD_REQUEST, $response->json());
        }

        $recommendations = $response->json()['data'] ?? [];
        $userIds = array_column($recommendations, 0); // Extract user IDs from recommendations

        // Fetch user details from Laravel database
        $users = $this->userController->model::whereIn('id', $userIds)->get();

        // Combine user details with scores
        $result = $users->map(function ($user) use ($recommendations) {
            $score = collect($recommendations)->firstWhere(0, $user->id)[1];
            return [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'biodata' => $user->biodata,
                'reputation' => $user->reputation,
                'score' => $score,
            ];
        });

        return $this->success('Recommendations retrieved successfully.', $result);
    }
}
