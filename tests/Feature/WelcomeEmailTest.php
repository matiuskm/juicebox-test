<?php

namespace Tests\Feature;

use App\Jobs\SendWelcomeEmail;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_welcome_mail(): void {
        Mail::fake();
        $user = User::factory()->create();

        (new SendWelcomeEmail($user))->handle();

        Mail::assertSent(WelcomeMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_command_dispatches_job_by_id_or_email(): void {
        Queue::fake();
        $user = User::factory()->create();

        $this->artisan('app:send-welcome-email', ['user' => $user->id])->assertSuccessful();
        $this->artisan('app:send-welcome-email', ['user' => $user->email])->assertSuccessful();

        Queue::assertPushed(SendWelcomeEmail::class, 2);
    }

    public function test_command_fails_for_unknown_user(): void {
        Queue::fake();

        $this->artisan('app:send-welcome-email', ['user' => 'nobody@example.com'])->assertFailed();

        Queue::assertNothingPushed();
    }
}
