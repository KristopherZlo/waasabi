<?php

use App\Services\NotificationService;
use App\Services\TopbarPromoService;

view()->composer(['layouts.app', 'layouts.support'], function ($view) {
    $payload = app(NotificationService::class)->buildPayload();
    $view->with([
        'unreadNotifications' => $payload['unreadPreview'],
        'unreadCount' => $payload['unreadCount'],
        'topbar_promo' => app(TopbarPromoService::class)->pickPromo(),
    ]);
});
