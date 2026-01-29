<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CaptchaService
{
    public function honeypotTripped(Request $request): bool
    {
        $candidates = [
            (string) $request->input('contact_time', ''),
            (string) $request->input('website', ''),
        ];

        foreach ($candidates as $value) {
            if (trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    public function isEnabled(string $action): bool
    {
        $config = (array) config('waasabi.captcha', []);
        if (!(bool) ($config['enabled'] ?? false)) {
            return false;
        }
        $siteKey = trim((string) ($config['site_key'] ?? ''));
        $secret = trim((string) ($config['secret'] ?? ''));
        if ($siteKey === '' || $secret === '') {
            return false;
        }
        $actions = (array) ($config['actions'] ?? []);
        return (bool) ($actions[$action] ?? false);
    }

    public function verify(Request $request): bool
    {
        $config = (array) config('waasabi.captcha', []);
        $provider = strtolower((string) ($config['provider'] ?? 'turnstile'));
        $secret = (string) ($config['secret'] ?? '');
        $token = (string) $request->input('cf-turnstile-response', '');

        if ($provider !== 'turnstile') {
            return true;
        }
        if ($secret === '' || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(4)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (\Throwable $e) {
            return false;
        }

        $payload = $response->json();
        return (bool) ($payload['success'] ?? false);
    }
}
