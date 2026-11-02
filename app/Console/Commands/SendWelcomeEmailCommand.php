<?php

namespace App\Console\Commands;

use App\Jobs\SendWelcomeEmail;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-welcome-email {user : The user ID or email}')]
#[Description('Dispatch the welcome email job for a user (for manual testing)')]
class SendWelcomeEmailCommand extends Command
{
    public function handle(): int {
        $key = $this->argument('user');
        $user = User::where(is_numeric($key) ? 'id' : 'email', $key)->first();

        if (! $user) {
            $this->error("User [{$key}] not found.");

            return self::FAILURE;
        }

        SendWelcomeEmail::dispatch($user);

        $this->info("Welcome email job dispatched for {$user->email}.");

        return self::SUCCESS;
    }
}
