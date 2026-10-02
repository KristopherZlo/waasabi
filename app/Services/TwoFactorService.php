<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(private readonly Google2FA $totp = new Google2FA) {}

    public function secret(): string
    {
        return $this->totp->generateSecretKey(32);
    }

    public function uri(User $user, string $secret): string
    {
        return $this->totp->getQRCodeUrl(config('app.name', 'waasabi'), $user->email, $secret);
    }

    public function qr(User $user, string $secret): string
    {
        $renderer = new ImageRenderer(new RendererStyle(220, 2), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($this->uri($user, $secret));

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function recoveryCodes(): array
    {
        return array_map(
            static fn () => strtoupper(implode('-', str_split(bin2hex(random_bytes(6)), 4))),
            range(1, 8),
        );
    }

    public function verify(User $user, string $code): bool
    {
        $code = trim($code);
        $secret = (string) $user->two_factor_secret;
        if ($secret !== '' && preg_match('/\A\d{6}\z/', $code) && $this->totp->verifyKey($secret, $code, 1)) {
            return true;
        }

        $normalized = strtoupper(str_replace([' ', '-'], '', $code));
        foreach ((array) $user->two_factor_recovery_codes as $index => $recoveryCode) {
            if (! hash_equals(str_replace('-', '', (string) $recoveryCode), $normalized)) {
                continue;
            }
            $codes = (array) $user->two_factor_recovery_codes;
            unset($codes[$index]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

            return true;
        }

        return false;
    }
}
