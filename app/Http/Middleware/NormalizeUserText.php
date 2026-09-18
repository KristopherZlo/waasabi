<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeUserText
{
    private const SECRET_FIELDS = ['password', 'password_confirmation', 'current_password', 'token', '_token', 'code'];

    public function handle(Request $request, Closure $next): Response
    {
        $request->merge($this->normalize($request->all()));

        return $next($request);
    }

    private function normalize(mixed $value, string $key = ''): mixed
    {
        if (is_array($value)) {
            return collect($value)->map(fn (mixed $item, string|int $itemKey) => $this->normalize($item, (string) $itemKey))->all();
        }
        if (! is_string($value) || in_array($key, self::SECRET_FIELDS, true)) {
            return $value;
        }

        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FFFD}]/u', '', $value) ?? '';
    }
}
