<?php

use App\Http\Middleware\EnsureAccountAge;
use App\Http\Middleware\EnsureNotBanned;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NormalizeUserText;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', 'throttle:web');
        $middleware->appendToGroup('web', SecurityHeaders::class);
        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->appendToGroup('web', EnsureNotBanned::class);
        $middleware->appendToGroup('web', NormalizeUserText::class);
        $middleware->appendToGroup('web', HandleInertiaRequests::class);
        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'account.age' => EnsureAccountAge::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
