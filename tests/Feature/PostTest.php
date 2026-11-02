<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_are_listed_with_pagination(): void {
        Post::factory()->count(20)->create();

        $this->getJson('/api/posts?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonStructure(['data' => [['id', 'title', 'content', 'author' => ['id', 'name']]], 'links', 'meta']);
    }

    public function test_single_post_can_be_shown(): void {
        $post = Post::factory()->create();

        $this->getJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.title', $post->title);
    }

    public function test_missing_post_returns_404(): void {
        $this->getJson('/api/posts/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    public function test_authenticated_user_can_create_post(): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/posts', ['title' => 'Hello', 'content' => 'World'])
            ->assertCreated()
            ->assertJsonPath('data.author.id', $user->id);

        $this->assertDatabaseHas('posts', ['title' => 'Hello', 'user_id' => $user->id]);
    }

    public function test_guest_cannot_create_post(): void {
        $this->postJson('/api/posts', ['title' => 'Hello', 'content' => 'World'])->assertUnauthorized();
    }

    public function test_guest_gets_401_even_without_json_accept_header(): void {
        $post = Post::factory()->create();

        $this->patch("/api/posts/{$post->id}", ['title' => 'Hacked'])->assertUnauthorized();
    }

    public function test_create_post_validates_input(): void {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/posts', ['title' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_author_can_partially_update_post(): void {
        $post = Post::factory()->create(['content' => 'Original content']);
        Sanctum::actingAs($post->user);

        $this->patchJson("/api/posts/{$post->id}", ['title' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated')
            ->assertJsonPath('data.content', 'Original content');
    }

    public function test_other_user_cannot_update_or_delete_post(): void {
        $post = Post::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/posts/{$post->id}", ['title' => 'Hacked'])->assertForbidden();
        $this->deleteJson("/api/posts/{$post->id}")->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => $post->title]);
    }

    public function test_author_can_delete_post(): void {
        $post = Post::factory()->create();
        Sanctum::actingAs($post->user);

        $this->deleteJson("/api/posts/{$post->id}")->assertNoContent();

        $this->assertModelMissing($post);
    }
}
