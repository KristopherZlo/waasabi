<?php

namespace App\Services;

use App\Models\TopbarPromo;
use Illuminate\Support\Facades\DB;

class TopbarPromoService
{
    public function pickPromo(): ?array
    {
        $now = now();
        $attempts = 3;
        while ($attempts > 0) {
            $attempts -= 1;
            $query = TopbarPromo::query()->where('is_active', true);
            $query->where(function ($sub) use ($now) {
                $sub->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })->where(function ($sub) use ($now) {
                $sub->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })->where(function ($sub) {
                $sub->whereNull('max_impressions')->orWhereColumn('impressions_count', '<', 'max_impressions');
            });
            $promo = $query->inRandomOrder()->first();

            if (! $promo) {
                return null;
            }

            $updated = TopbarPromo::query()
                ->where('id', $promo->id)
                ->where(function ($sub) {
                    $sub->whereNull('max_impressions')->orWhereColumn('impressions_count', '<', 'max_impressions');
                })
                ->update(['impressions_count' => DB::raw('impressions_count + 1')]);

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
}
