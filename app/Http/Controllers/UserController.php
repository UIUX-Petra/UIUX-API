<?php

namespace App\Http\Controllers;

use App\Models\User;
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

    public function follow(string $id)
    {

        $idCurrUser = '9d9c7014-4b18-4f5f-a3fc-5c60fadeb601'; //Blum di test, karena lek postman ga nyimpen session
        $currUser = User::findOrFail($idCurrUser); 

        $mauDiFolo = User::findOrFail($id);

        if($currUser == $mauDiFolo){
            return $this->error('You Cannot Follow Yourself');
        }

        if ($mauDiFolo) {     //jika user yang mau difolo ada di DB USER, bisa folo

            if (($currUser->following()->where('followed_id', $mauDiFolo->id))->exists()) { //unFoll
                $currUser->following()->detach($mauDiFolo->id);
            } else {
                $currUser->following()->attach($mauDiFolo->id, ['id' => Str::uuid()]); //Folo
            }
        }
        return $this->success('Successfully retrieved data', [
            'user' => $currUser->load(['following','followers'])
        ]);
    }

    public function getFollowing(User $id)
    {
        $user = User::find($id);
        $followings = $user->following;
        return $this->success('Successfully retrieved data', $followings);
    }

    public function getFollower(User $id)
    {
        $user = User::find($id);
        $followersUser = $user->followers;
        return $this->success('Successfully retrieved data', $followersUser);
    }
}

