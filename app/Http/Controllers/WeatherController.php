<?php

namespace App\Http\Controllers;

use App\Services\WeatherService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class WeatherController extends Controller
{
    public function __invoke(WeatherService $weather): JsonResponse {
        try {
            return response()->json(['data' => $weather->current()]);
        } catch (ConnectionException|RequestException $e) {
            report($e);

            // Upstream is down: serve the last good reading rather than nothing.
            if ($lastKnown = $weather->lastKnown()) {
                return response()->json(['data' => $lastKnown, 'stale' => true]);
            }

            return response()->json(
                ['message' => 'Weather service is currently unavailable. Please try again later.'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }
}
