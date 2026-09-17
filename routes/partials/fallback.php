<?php

use App\Services\UserPayloadService;
use Illuminate\Support\Facades\Route;

Route::fallback(function () {
    return response()
        ->view('errors.404', ['current_user' => app(UserPayloadService::class)->currentUserPayload()], 404);
})->name('not-found');
