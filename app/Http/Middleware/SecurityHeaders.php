<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        view()->share('csp_nonce', $nonce);

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $csp = $this->buildCsp($nonce, $request);
        $cspHeader = (bool) config('waasabi.security.csp_report_only', false)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
        $headers->set($cspHeader, $csp);

        return $response;
    }

    private function buildCsp(string $nonce, Request $request): string
    {
        $scriptSrc = array_merge(
            ["'self'", "'nonce-{$nonce}'", 'https://challenges.cloudflare.com'],
            (array) config('waasabi.security.csp_extra_script_src', []),
        );
        $styleSrc = array_merge(
            ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
            (array) config('waasabi.security.csp_extra_style_src', []),
        );
        $connectSrc = array_merge(
            ["'self'", 'https://challenges.cloudflare.com'],
            (array) config('waasabi.security.csp_extra_connect_src', []),
        );
        $frameSrc = array_merge(
            ["'self'", 'https://challenges.cloudflare.com'],
            (array) config('waasabi.security.csp_extra_frame_src', []),
        );

        if (app()->environment('local')) {
            $devHttp = ['http://localhost:5173', 'http://127.0.0.1:5173'];
            $devWs = ['ws://localhost:5173', 'ws://127.0.0.1:5173'];
            $connectSrc = array_merge($connectSrc, $devHttp, $devWs);
            $scriptSrc = array_merge($scriptSrc, $devHttp, ["'unsafe-eval'"]);
            $styleSrc = array_merge($styleSrc, $devHttp);
        }

        $directives = [
            "default-src 'self'",
            'script-src ' . implode(' ', array_unique($scriptSrc)),
            'style-src ' . implode(' ', array_unique($styleSrc)),
            "img-src 'self' data: https:",
            "font-src 'self' data: https://fonts.gstatic.com",
            'connect-src ' . implode(' ', array_unique($connectSrc)),
            'frame-src ' . implode(' ', array_unique($frameSrc)),
            "form-action 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
        ];

        return implode('; ', $directives);
    }
}
