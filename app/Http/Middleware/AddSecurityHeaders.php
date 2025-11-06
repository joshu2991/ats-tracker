<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Check if we're in development mode
        $isDevelopment = app()->environment('local', 'testing');

        // Content Security Policy
        // In development, allow Vite dev server (localhost:5173)
        if ($isDevelopment) {
            // Allow localhost and 127.0.0.1 on port 5173 for Vite dev server
            // CSP requires explicit ports, no wildcard support
            $csp = "default-src 'self' http://localhost:5173 http://127.0.0.1:5173 ws://localhost:5173 ws://127.0.0.1:5173; ".
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:5173 http://127.0.0.1:5173 https://www.googletagmanager.com https://www.google-analytics.com; ".
                "style-src 'self' 'unsafe-inline' http://localhost:5173 http://127.0.0.1:5173 https://fonts.bunny.net https://fonts.googleapis.com; ".
                "font-src 'self' http://localhost:5173 http://127.0.0.1:5173 https://fonts.bunny.net https://fonts.gstatic.com data:; ".
                "img-src 'self' data: https: http://localhost:5173 http://127.0.0.1:5173; ".
                "connect-src 'self' http://localhost:5173 http://127.0.0.1:5173 ws://localhost:5173 ws://127.0.0.1:5173 https://www.google-analytics.com; ".
                "worker-src 'self' blob: data:; ".
                "frame-ancestors 'none'; ".
                "base-uri 'self'; ".
                "form-action 'self';";
        } else {
            // Production CSP - stricter (no unsafe-eval)
            $csp = "default-src 'self'; ".
                "script-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://www.google-analytics.com; ".
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com; ".
                "font-src 'self' https://fonts.bunny.net https://fonts.gstatic.com data:; ".
                "img-src 'self' data: https:; ".
                "connect-src 'self' https://www.google-analytics.com; ".
                "worker-src 'self' blob: data:; ".
                "frame-ancestors 'none'; ".
                "base-uri 'self'; ".
                "form-action 'self';";
        }

        $response->headers->set('Content-Security-Policy', $csp);

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions policy
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), speaker=()'
        );

        // XSS Protection (legacy, but still useful)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}
