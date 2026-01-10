<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            $forcedUrl = config('app.url');
            if (!empty($forcedUrl)) {
                URL::forceRootUrl($forcedUrl);
            } elseif ($this->app->environment('local', 'testing')) {
                $root = request()->getSchemeAndHttpHost() . request()->getBaseUrl();
                URL::forceRootUrl($root);
            }
        }

        Gate::define('admin', function (User $user): bool {
            return $user->isAdmin();
        });

        Gate::define('moderate', function (User $user): bool {
            return $user->hasRole('moderator');
        });

        Gate::define('support', function (User $user): bool {
            return $user->canPerform('support');
        });
    }
}
