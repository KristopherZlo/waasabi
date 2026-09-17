<?php

namespace Tests\Unit;

use App\Services\CaptchaService;
use Illuminate\Http\Request;
use Tests\TestCase;

class CaptchaServiceTest extends TestCase
{
    public function test_unknown_provider_fails_closed(): void
    {
        config()->set('hub.captcha.provider', 'unknown-provider');
        config()->set('hub.captcha.secret', 'test-secret');

        $request = Request::create('/', 'POST', ['cf-turnstile-response' => 'token']);

        $this->assertFalse(app(CaptchaService::class)->verify($request));
    }
}
