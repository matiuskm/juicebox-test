<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_shown_with_post_count(): void {
        $user = User::factory()->has(Post::factory()->count(3))->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.posts_count', 3)
            // Another user's email is not exposed.
            ->assertJsonMissingPath('data.email');
    }

    public function test_user_sees_own_email(): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/users/{$user->id}")->assertJsonPath('data.email', $user->email);
    }

    public function test_users_are_listed_with_pagination(): void {
        User::factory()->count(19)->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20);
    }

    public function test_users_endpoint_requires_authentication(): void {
        $user = User::factory()->create();

        $this->getJson("/api/users/{$user->id}")->assertUnauthorized();
    }
}
