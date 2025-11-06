<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AddSecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle throttle/rate limit errors (429)
        $exceptions->render(function (\Illuminate\Http\Request $request, ThrottleRequestsException $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Too many requests. Please wait 1 hour before trying again.',
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? 3600,
                ], 429);
            }

            // For Inertia requests, redirect back with error message
            return back()->withErrors([
                'rate_limit' => 'You have analyzed too many resumes. Please wait 1 hour before trying again.',
            ])->with('rate_limit_info', [
                'retry_after' => $e->getHeaders()['Retry-After'] ?? 3600,
                'message' => 'You have reached the rate limit. Please wait 1 hour before analyzing another resume.',
            ]);
        });

        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            // Handle 429 Too Many Requests
            if ($response->getStatusCode() === 429) {
                $request = request();
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => 'Too many requests. Please wait 1 hour before trying again.',
                    ], 429);
                }

                // For Inertia requests, redirect back with error message
                return back()->withErrors([
                    'rate_limit' => 'You have analyzed too many resumes. Please wait 1 hour before trying again.',
                ]);
            }

            if (in_array($response->getStatusCode(), [404, 405])) {
                return Inertia::render(
                    $response->getStatusCode() === 404 ? 'NotFound' : 'MethodNotAllowed',
                    ['status' => $response->getStatusCode()],
                )->toResponse(request());
            }

            return $response;
        });
    })->create();
