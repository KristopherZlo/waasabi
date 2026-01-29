<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\SchemaGuard;
use Illuminate\Http\Request;

class AuditLogService
{
    public function log(
        Request $request,
        string $event,
        ?User $actor = null,
        array $meta = [],
        ?string $targetType = null,
        ?string $targetId = null
    ): void {
        if (!SchemaGuard::hasTable('audit_logs')) {
            return;
        }

        $userId = $actor?->id ?? $request->user()?->id;
        $userAgent = substr((string) $request->userAgent(), 0, 255);

        AuditLog::create([
            'user_id' => $userId,
            'event' => $event,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'meta' => $meta !== [] ? $meta : null,
        ]);
    }
}
