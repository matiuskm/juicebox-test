<?php

namespace Tests\Feature;

use App\Jobs\SendWelcomeEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuthTest extends TestCase {
    use RefreshDatabase;

    public function test_user_can_register_and_welcome_email_is_queued() {
        Queue::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'john@example.com')
            ->assertJsonStructure(['data' => ['id', 'name', 'email'], 'token'])
            ->assertJsonMissingPath('data.password');

        // Assert that the SendWelcomeEmail job was pushed to the queue
        Queue::assertPushed(SendWelcomeEmail::class, fn ($job) => $job->user->email === 'john@example.com');
    }

    public function test_register_validates_input() {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'email' => 'taken@example.com',
            'password' => 'short',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_user_can_login_with_valid_credentials() {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['id'], 'token']);
    }

    public function test_login_fails_with_wrong_password(): void {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnauthorized()
            ->assertJsonValidationErrors('email');
    }

    public function test_logout_revokes_the_current_token(): void {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_requires_authentication(): void {
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
