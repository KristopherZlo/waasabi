<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TopbarPromo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminPromoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        TopbarPromo::create($this->validatedData($request));

        return redirect()->route('admin', ['tab' => 'promos']);
    }

    public function update(Request $request, TopbarPromo $promo): RedirectResponse
    {
        $promo->update($this->validatedData($request));

        return redirect()->route('admin', ['tab' => 'promos']);
    }

    public function destroy(TopbarPromo $promo): RedirectResponse
    {
        $promo->delete();

        return redirect()->route('admin', ['tab' => 'promos']);
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_impressions' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'unlimited' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'label' => $data['label'],
            'url' => $data['url'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'max_impressions' => $request->boolean('unlimited') ? null : ($data['max_impressions'] ?? null),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
