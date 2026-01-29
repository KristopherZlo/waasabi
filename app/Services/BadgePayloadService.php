<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BadgePayloadService
{
    public function payload(UserBadge $badge, array $catalogMap): array
    {
        $catalog = $catalogMap[$badge->badge_key] ?? null;
        $defaultName = $catalog['name'] ?? Str::title(str_replace('_', ' ', (string) $badge->badge_key));
        $defaultDescription = $catalog['description'] ?? '';
        $iconPath = $catalog['icon'] ?? '';
        $issuedAt = $badge->issued_at ? $badge->issued_at->format('Y-m-d') : '';

        return [
            'id' => $badge->id,
            'key' => $badge->badge_key,
            'label' => $badge->badge_name ?: $defaultName,
            'description' => $badge->badge_description ?: $defaultDescription,
            'reason' => $badge->reason ?? '',
            'issued_at' => $issuedAt,
            'icon' => $iconPath !== '' ? asset(ltrim($iconPath, '/')) : '',
        ];
    }

    public function forUser(User $user, array $badgeCatalog): array
    {
        if (!$this->safeHasTable('user_badges')) {
            return [];
        }

        $catalogMap = collect($badgeCatalog)->keyBy('key')->all();

        return $user->badges()
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (UserBadge $badge) => $this->payload($badge, $catalogMap))
            ->values()
            ->all();
    }

    private function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
