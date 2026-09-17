<?php

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CaptchaService;
use Illuminate\Http\Request;

if (! function_exists('logAuditEvent')) {
    function logAuditEvent(
        Request $request,
        string $event,
        ?User $actor = null,
        array $meta = [],
        ?string $targetType = null,
        ?string $targetId = null
    ): void {
        app(AuditLogService::class)->log($request, $event, $actor, $meta, $targetType, $targetId);
    }
}

if (! function_exists('honeypotTripped')) {
    function honeypotTripped(Request $request): bool
    {
        return app(CaptchaService::class)->honeypotTripped($request);
    }
}

if (! function_exists('captchaEnabled')) {
    function captchaEnabled(string $action): bool
    {
        return app(CaptchaService::class)->isEnabled($action);
    }
}

if (! function_exists('verifyCaptcha')) {
    function verifyCaptcha(Request $request): bool
    {
        return app(CaptchaService::class)->verify($request);
    }
}
