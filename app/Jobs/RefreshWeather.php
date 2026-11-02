<?php

namespace App\Jobs;

use App\Services\WeatherService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Keeps the weather cache warm so API requests rarely wait on the upstream call.
 */
class RefreshWeather implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(WeatherService $weather): void {
        $weather->refresh();
    }
}
