<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Health check endpoint.
     *
     * Returns application health status including database, cache, and rate limit configuration.
     * This endpoint is designed to be fast and lightweight for monitoring services.
     */
    public function index(): JsonResponse
    {
        $status = 'ok';
        $checks = [];

        // Database check
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'connected';
        } catch (\Exception $e) {
            $status = 'error';
            $checks['database'] = 'disconnected';
        }

        // Cache check
        try {
            $testKey = 'health_check_'.time();
            Cache::put($testKey, 'test', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);
            $checks['cache'] = $value === 'test' ? 'working' : 'failed';
        } catch (\Exception $e) {
            $status = 'error';
            $checks['cache'] = 'failed';
        }

        // Rate limit configuration (for visibility)
        $checks['rate_limits'] = [
            'resume_analyze' => '10 per hour',
            'feedback' => '5 per hour',
        ];

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'version' => '1.0.0',
        ], $status === 'ok' ? 200 : 503);
    }
}
