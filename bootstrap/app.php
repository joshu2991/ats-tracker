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

        // Handle all other exceptions for Inertia requests
        $exceptions->render(function (\Illuminate\Http\Request $request, \Throwable $e) {
            // Only handle web requests (not API)
            if ($request->expectsJson() || $request->is('api/*')) {
                return null; // Let Laravel handle it normally
            }

            // For Inertia requests, render error page
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $message = $e->getMessage();

            // Don't expose sensitive error messages in production
            if (! config('app.debug')) {
                $message = 'An unexpected error occurred. Please try again later.';
            }

            return Inertia::render('Error', [
                'status' => $status,
                'message' => $message,
            ])->toResponse($request)->setStatusCode($status);
        });

        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            $request = request();

            // Skip API requests
            if ($request->expectsJson() || $request->is('api/*')) {
                return $response;
            }

            $status = $response->getStatusCode();

            // Handle specific status codes with Inertia pages
            if ($status === 404) {
                return Inertia::render('NotFound', ['status' => $status])->toResponse($request);
            }

            if ($status === 405) {
                return Inertia::render('MethodNotAllowed', ['status' => $status])->toResponse($request);
            }

            // Handle 500+ errors with Error page
            if ($status >= 500) {
                return Inertia::render('Error', [
                    'status' => $status,
                    'message' => config('app.debug') ? null : 'An unexpected error occurred. Please try again later.',
                ])->toResponse($request);
            }

            return $response;
        });
    })->create();
