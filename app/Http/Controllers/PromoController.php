<?php

namespace App\Http\Controllers;

use App\Models\TopbarPromo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PromoController extends Controller
{
    public function click(TopbarPromo $promo): RedirectResponse
    {
        abort_unless(
            $promo->is_active
            && (! $promo->starts_at || $promo->starts_at->isPast())
            && (! $promo->ends_at || $promo->ends_at->isFuture()),
            404,
        );

        TopbarPromo::query()
            ->whereKey($promo->id)
            ->update(['clicks_count' => DB::raw('clicks_count + 1')]);

        return redirect()->away($promo->url);
    }
}
