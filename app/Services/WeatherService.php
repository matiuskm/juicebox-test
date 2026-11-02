<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Current weather for Perth, Australia from Open-Meteo (free, no API key).
 */
class WeatherService
{
    public const CACHE_KEY = 'weather:perth';

    public const LAST_KNOWN_KEY = 'weather:perth:last-known';

    /**
     * Cached reading; only calls the API on a cache miss.
     *
     * @throws ConnectionException|RequestException
     */
    public function current(): array {
        return Cache::remember(self::CACHE_KEY, $this->ttl(), fn () => $this->fetch());
    }

    /**
     * Always calls the API and overwrites the cache (used by the scheduled job).
     *
     * @throws ConnectionException|RequestException
     */
    public function refresh(): array {
        $data = $this->fetch();

        Cache::put(self::CACHE_KEY, $data, $this->ttl());

        return $data;
    }

    /**
     * Last successful reading, kept without expiry as a fallback for outages.
     */
    public function lastKnown(): ?array {
        return Cache::get(self::LAST_KNOWN_KEY);
    }

    /**
     * @throws ConnectionException|RequestException
     */
    private function fetch(): array {
        $json = Http::baseUrl(config('services.weather.url'))
            ->acceptJson()
            ->timeout(5)
            ->retry(2, 200)
            ->get('forecast', [
                'latitude' => -31.9523,
                'longitude' => 115.8613,
                'timezone' => 'Australia/Perth',
                'forecast_days' => 1,
                'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,wind_speed_10m,weather_code',
                'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_probability_max',
            ])
            ->throw()
            ->json();

        // Map to our own shape so clients don't depend on the provider's format.
        $data = [
            'location' => 'Perth, Australia',
            'observed_at' => data_get($json, 'current.time'),
            'temperature' => data_get($json, 'current.temperature_2m'),
            'feels_like' => data_get($json, 'current.apparent_temperature'),
            'humidity' => data_get($json, 'current.relative_humidity_2m'),
            'wind_speed' => data_get($json, 'current.wind_speed_10m'),
            'weather_code' => data_get($json, 'current.weather_code'),
            'today' => [
                'min' => data_get($json, 'daily.temperature_2m_min.0'),
                'max' => data_get($json, 'daily.temperature_2m_max.0'),
                'precipitation_probability' => data_get($json, 'daily.precipitation_probability_max.0'),
            ],
            'units' => [
                'temperature' => '°C',
                'wind_speed' => 'km/h',
                'humidity' => '%',
            ],
            'fetched_at' => now()->toIso8601String(),
        ];

        Cache::forever(self::LAST_KNOWN_KEY, $data);

        return $data;
    }

    private function ttl(): int {
        return (int) config('services.weather.cache_ttl');
    }
}
