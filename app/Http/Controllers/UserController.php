<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection {
        $users = User::withCount('posts')->latest()->paginate(min($request->integer('per_page', 15), 100));

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource {
        return new UserResource($user->loadCount('posts'));
    }
}
