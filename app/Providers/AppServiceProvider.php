<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Define custom rate limiters with custom responses
        RateLimiter::for('resume-analyze', function (Request $request) {
            return Limit::perHour(10)->by($request->ip())->response(function (Request $request, array $headers) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'Too many requests. Please wait 1 hour before trying again.',
                        'errors' => [
                            'rate_limit' => ['You have analyzed too many resumes. Please wait 1 hour before trying again.'],
                        ],
                    ], 429, $headers);
                }

                // For Inertia requests, redirect back with error message
                return back()->withErrors([
                    'rate_limit' => 'You have analyzed too many resumes. Please wait 1 hour before trying again.',
                ]);
            });
        });

        RateLimiter::for('feedback', function (Request $request) {
            return Limit::perHour(5)->by($request->ip())->response(function (Request $request, array $headers) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'Too many requests. Please wait 1 hour before trying again.',
                        'errors' => [
                            'rate_limit' => ['You have submitted too much feedback. Please wait 1 hour before trying again.'],
                        ],
                    ], 429, $headers);
                }

                // For Inertia requests, redirect back with error message
                return back()->withErrors([
                    'rate_limit' => 'You have submitted too much feedback. Please wait 1 hour before trying again.',
                ]);
            });
        });
    }
}
