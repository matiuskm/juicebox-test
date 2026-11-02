<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::with('user')->latest()->paginate(min($request->integer('per_page', 15), 100));

        return PostResource::collection($posts);
    }

    public function store(StorePostRequest $request): PostResource {
        $post = $request->user()->posts()->create($request->validated());

        return new PostResource($post->load('user'));
    }

    public function show(Post $post): PostResource {
        return new PostResource($post->load('user'));
    }

    public function update(UpdatePostRequest $request, Post $post): PostResource {
        $post->update($request->validated());

        return new PostResource($post->load('user'));
    }

    public function destroy(Request $request, Post $post): \Illuminate\Http\Response {
        Gate::authorize('delete', $post);

        $post->delete();

        return response()->noContent();
    }
}
