<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_local_csp_allows_fonts_from_vite(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $response = (new SecurityHeaders)->handle(
            Request::create('/'),
            fn (): Response => new Response,
        );

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("img-src 'self' data: blob: https:", $csp);
        $this->assertStringContainsString("font-src 'self' data:", $csp);
        $this->assertStringContainsString('http://localhost:5173', $csp);
        $this->assertStringContainsString('http://127.0.0.1:5173', $csp);
    }
}
