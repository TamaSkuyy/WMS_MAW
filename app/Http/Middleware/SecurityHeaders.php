<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate nonce BEFORE response so @vite and @routes can inject it
        $nonce = base64_encode(random_bytes(16));
        Vite::useCspNonce($nonce);

        // Share nonce to all views for Ziggy @routes directive
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // HSTS — always in production, regardless of $request->secure()
        // (behind proxy, $request->secure() may return false)
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // CSP — strict nonce-based, 'strict-dynamic' allows scripts loaded by trusted scripts
        $response->headers->set(
            'Content-Security-Policy',
            implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'",
                "style-src 'self' 'unsafe-inline' fonts.bunny.net fonts.googleapis.com",
                "img-src 'self' data: blob:",
                "font-src 'self' fonts.bunny.net fonts.gstatic.com",
                "connect-src 'self' ws: wss:",
                "frame-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ])
        );

        return $response;
    }
}
