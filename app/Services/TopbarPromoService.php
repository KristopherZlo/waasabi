<?php

namespace App\Services;

use App\Models\TopbarPromo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TopbarPromoService
{
    public function getPromos(bool $onlyActive = true): array
    {
        if (!$this->safeHasTable('topbar_promos')) {
            return [];
        }

        $query = DB::table('topbar_promos')->select('id', 'label', 'url', 'is_active', 'sort_order');
        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => (string) $row->label,
                'url' => (string) $row->url,
                'is_active' => (bool) $row->is_active,
                'sort_order' => (int) $row->sort_order,
            ])
            ->all();
    }

    public function pickPromo(): ?array
    {
        if (!$this->safeHasTable('topbar_promos')) {
            return null;
        }

        $hasStartsAt = $this->safeHasColumn('topbar_promos', 'starts_at');
        $hasEndsAt = $this->safeHasColumn('topbar_promos', 'ends_at');
        $hasMaxImpressions = $this->safeHasColumn('topbar_promos', 'max_impressions');
        $hasImpressionsCount = $this->safeHasColumn('topbar_promos', 'impressions_count');

        $now = now();
        $attempts = 3;
        while ($attempts > 0) {
            $attempts -= 1;
            $query = TopbarPromo::query()->where('is_active', true);
            if ($hasStartsAt) {
                $query->where(function ($sub) use ($now) {
                    $sub->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                });
            }
            if ($hasEndsAt) {
                $query->where(function ($sub) use ($now) {
                    $sub->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                });
            }
            if ($hasMaxImpressions && $hasImpressionsCount) {
                $query->where(function ($sub) {
                    $sub->whereNull('max_impressions')->orWhereColumn('impressions_count', '<', 'max_impressions');
                });
            }
            $promo = $query->inRandomOrder()->first();

            if (!$promo) {
                return null;
            }

            $updated = 1;
            if ($hasImpressionsCount) {
                $updateQuery = TopbarPromo::query()->where('id', $promo->id);
                if ($hasMaxImpressions) {
                    $updateQuery->where(function ($sub) {
                        $sub->whereNull('max_impressions')->orWhereColumn('impressions_count', '<', 'max_impressions');
                    });
                }
                $updated = $updateQuery->update(['impressions_count' => DB::raw('impressions_count + 1')]);
            }

            if ($updated > 0) {
                return [
                    'id' => (int) $promo->id,
                    'label' => (string) $promo->label,
                    'url' => (string) $promo->url,
                ];
            }
        }

        return null;
    }

    private function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function safeHasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
