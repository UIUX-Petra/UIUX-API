<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use Illuminate\Container\Attributes\CurrentUser;

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

    public function create($data)
    {
        Log::info($data);
        return $this->model::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }

    public function getByEmail(string $email){
        $userDiCari = $this->model::where('email', $email)->with('relations')->get()->first();
        return $this->success('Successfully retrieved data', $userDiCari);
    }

    public function follow(string $id, Request $reqs)   
    {
        $currUser = $this->model::where('email', $reqs->emailCurr)->get()->first();
        $mauDiFolo  = $this->model::where('id', $id)->get()->first();

        if(!$currUser || !$mauDiFolo){
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
        $mauDiFolo=$mauDiFolo->load(['following','followers']);
        
        return $this->success('Successfully retrieved data', [
            'user' => $currUser->load(['following', 'followers']),
            'countFollowers'=>count($mauDiFolo['followers'])
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
}

