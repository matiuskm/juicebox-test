<?php

use App\Jobs\RefreshWeather;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Refresh as often as the cache expires, so the cache stays warm.
Schedule::job(new RefreshWeather)->everyFifteenMinutes();
