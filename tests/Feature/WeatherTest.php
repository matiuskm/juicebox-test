<?php

namespace Tests\Feature;

use App\Jobs\RefreshWeather;
use App\Services\WeatherService;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function fakeApi(float $temperature = 21.5): void {
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'time' => '2026-11-02T14:00',
                    'temperature_2m' => $temperature,
                    'apparent_temperature' => 20.1,
                    'relative_humidity_2m' => 55,
                    'wind_speed_10m' => 18.4,
                    'weather_code' => 1,
                ],
                'daily' => [
                    'temperature_2m_min' => [14.2],
                    'temperature_2m_max' => [26.8],
                    'precipitation_probability_max' => [10],
                ],
            ]),
        ]);
    }

    public function test_returns_current_weather_for_perth(): void {
        $this->fakeApi();

        $this->getJson('/api/weather')
            ->assertOk()
            ->assertJsonPath('data.location', 'Perth, Australia')
            ->assertJsonPath('data.temperature', 21.5)
            ->assertJsonPath('data.today.max', 26.8);

        Http::assertSent(fn ($request) => $request['latitude'] == -31.9523 && $request['timezone'] === 'Australia/Perth');
    }

    public function test_weather_is_cached_between_requests(): void {
        $this->fakeApi();

        $this->getJson('/api/weather')->assertOk();
        $this->getJson('/api/weather')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_returns_503_when_api_fails_and_nothing_cached(): void {
        Http::fake(['api.open-meteo.com/*' => Http::response(null, 500)]);

        $this->getJson('/api/weather')
            ->assertServiceUnavailable()
            ->assertJsonStructure(['message']);
    }

    public function test_serves_last_known_data_when_api_fails(): void {
        $this->fakeApi(19.5);
        $this->getJson('/api/weather')->assertOk();
        Cache::forget(WeatherService::CACHE_KEY); // simulate TTL expiry

        // Stubs are matched in registration order, so swap in a fresh fake that only fails.
        Http::swap(new Factory);
        Http::fake(['api.open-meteo.com/*' => Http::response(null, 500)]);

        $this->getJson('/api/weather')
            ->assertOk()
            ->assertJsonPath('stale', true)
            ->assertJsonPath('data.temperature', 19.5);
    }

    public function test_refresh_job_overwrites_the_cache(): void{
        $this->fakeApi(30.5);

        Cache::put(WeatherService::CACHE_KEY, ['temperature' => 10], 900);

        RefreshWeather::dispatchSync();

        $this->assertSame(30.5, Cache::get(WeatherService::CACHE_KEY)['temperature']);
    }
}
