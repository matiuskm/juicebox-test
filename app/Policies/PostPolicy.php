<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Only the author can update the post.
     */
    public function update(User $user, Post $post): bool {
        return $user->id === $post->user_id;
    }

    /**
     * Only the author can delete the post.
     */
    public function delete(User $user, Post $post): bool {
        return $user->id === $post->user_id;
    }
}
