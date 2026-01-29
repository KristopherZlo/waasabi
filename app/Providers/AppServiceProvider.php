<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/helpers.php');
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

        RateLimiter::for('web', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();
            $guestIpPerMinute = (int) config('waasabi.limits.web.guest_ip_per_minute', 300);
            $userPerMinute = (int) config('waasabi.limits.web.user_per_minute', 1200);
            $userIpPerMinute = (int) config('waasabi.limits.web.user_ip_per_minute', 800);

            if ($userId) {
                return [
                    Limit::perMinute($userPerMinute)->by('user|'.$userId),
                    Limit::perMinute($userIpPerMinute)->by($ip),
                ];
            }

            return Limit::perMinute($guestIpPerMinute)->by($ip);
        });

        RateLimiter::for('login', function (Request $request) {
            $emailKey = Str::lower((string) $request->input('email'));
            $ipLimit = (int) config('waasabi.limits.auth.login_ip_per_minute', 10);
            $emailIpLimit = (int) config('waasabi.limits.auth.login_email_ip_per_minute', 8);

            return [
                Limit::perMinute($ipLimit)->by($request->ip()),
                Limit::perMinute($emailIpLimit)->by($emailKey.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            $emailKey = Str::lower((string) $request->input('email'));
            $ipLimit = (int) config('waasabi.limits.auth.register_ip_per_minute', 5);
            $emailLimit = (int) config('waasabi.limits.auth.register_email_per_minute', 3);

            return [
                Limit::perMinute($ipLimit)->by($request->ip()),
                Limit::perMinute($emailLimit)->by($emailKey),
            ];
        });

        RateLimiter::for('verification', function (Request $request) {
            $ipLimit = (int) config('waasabi.limits.auth.verification_ip_per_minute', 6);
            return Limit::perMinute($ipLimit)->by($request->ip());
        });

        RateLimiter::for('publish', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();
            $perMinute = (int) config('waasabi.limits.content.publish_per_minute', 6);

            if ($userId) {
                return [
                    Limit::perMinute($perMinute)->by('user|'.$userId),
                    Limit::perMinute($perMinute)->by('ip|'.$ip),
                ];
            }

            return Limit::perMinute($perMinute)->by('ip|'.$ip);
        });

        RateLimiter::for('comments', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();
            $perMinute = (int) config('waasabi.limits.content.comment_per_minute', 12);

            if ($userId) {
                return [
                    Limit::perMinute($perMinute)->by('user|'.$userId),
                    Limit::perMinute($perMinute)->by('ip|'.$ip),
                ];
            }

            return Limit::perMinute($perMinute)->by('ip|'.$ip);
        });

        RateLimiter::for('reviews', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();
            $perMinute = (int) config('waasabi.limits.content.review_per_minute', 6);

            if ($userId) {
                return [
                    Limit::perMinute($perMinute)->by('user|'.$userId),
                    Limit::perMinute($perMinute)->by('ip|'.$ip),
                ];
            }

            return Limit::perMinute($perMinute)->by('ip|'.$ip);
        });

        RateLimiter::for('reports', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();
            $perMinute = (int) config('waasabi.limits.content.report_per_minute', 6);

            if ($userId) {
                return [
                    Limit::perMinute($perMinute)->by('user|'.$userId),
                    Limit::perMinute($perMinute)->by('ip|'.$ip),
                ];
            }

            return Limit::perMinute($perMinute)->by('ip|'.$ip);
        });

        RateLimiter::for('post-actions', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();
            $perMinute = (int) config('waasabi.limits.content.post_action_per_minute', 30);
            $ipPerMinute = (int) config('waasabi.limits.content.post_action_ip_per_minute', 60);

            if ($userId) {
                return [
                    Limit::perMinute($perMinute)->by('user|'.$userId),
                    Limit::perMinute($ipPerMinute)->by('ip|'.$ip),
                ];
            }

            return Limit::perMinute($ipPerMinute)->by('ip|'.$ip);
        });

        RateLimiter::for('uploads', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();
            $perMinute = (int) config('waasabi.limits.uploads.images_per_minute', 20);
            $perMinuteIp = (int) config('waasabi.limits.uploads.images_per_minute_ip', 40);
            $perDayUser = (int) config('waasabi.limits.uploads.images_per_day_user', 200);

            if ($userId) {
                return [
                    Limit::perMinute($perMinute)->by('user|'.$userId),
                    Limit::perMinute($perMinuteIp)->by('ip|'.$ip),
                    Limit::perDay($perDayUser)->by('user|'.$userId),
                ];
            }

            return Limit::perMinute($perMinuteIp)->by('ip|'.$ip);
        });

        RateLimiter::for('support-ticket', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();
            $perMinute = (int) config('waasabi.limits.support.tickets_per_minute', 6);

            if ($userId) {
                return [
                    Limit::perMinute($perMinute)->by('user|'.$userId),
                    Limit::perMinute($perMinute)->by('ip|'.$ip),
                ];
            }

            return Limit::perMinute($perMinute)->by('ip|'.$ip);
        });

        RateLimiter::for('support-message', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();
            $perMinute = (int) config('waasabi.limits.support.messages_per_minute', 12);

            if ($userId) {
                return [
                    Limit::perMinute($perMinute)->by('user|'.$userId),
                    Limit::perMinute($perMinute)->by('ip|'.$ip),
                ];
            }

            return Limit::perMinute($perMinute)->by('ip|'.$ip);
        });

        RateLimiter::for('profile-media', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();
            $perMinute = (int) config('waasabi.limits.profile.media_per_minute', 6);

            if ($userId) {
                return [
                    Limit::perMinute($perMinute)->by('user|'.$userId),
                    Limit::perMinute($perMinute)->by('ip|'.$ip),
                ];
            }

            return Limit::perMinute($perMinute)->by('ip|'.$ip);
        });

        RateLimiter::for('profile-follow', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();
            $perMinute = (int) config('waasabi.limits.profile.follow_per_minute', 20);

            if ($userId) {
                return [
                    Limit::perMinute($perMinute)->by('user|'.$userId),
                    Limit::perMinute($perMinute)->by('ip|'.$ip),
                ];
            }

            return Limit::perMinute($perMinute)->by('ip|'.$ip);
        });

        RateLimiter::for('reading-progress', function (Request $request) {
            $ip = $request->ip();
            $perMinute = (int) config('waasabi.limits.reading.progress_per_minute', 60);
            return Limit::perMinute($perMinute)->by($ip);
        });

        RateLimiter::for('read-later', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();
            $perMinute = (int) config('waasabi.limits.reading.read_later_per_minute', 30);

            if ($userId) {
                return [
                    Limit::perMinute($perMinute)->by('user|'.$userId),
                    Limit::perMinute($perMinute)->by('ip|'.$ip),
                ];
            }

            return Limit::perMinute($perMinute)->by('ip|'.$ip);
        });

        Gate::define('admin', function (User $user): bool {
            return $user->isAdmin();
        });

        Gate::define('moderate', function (User $user): bool {
            return $user->hasRole('moderator');
        });

        Gate::define('support', function (User $user): bool {
            return $user->canPerform('support');
        });

        Gate::define('publish', function (User $user): bool {
            if (($user->is_banned ?? false)) {
                return false;
            }
            return $user->canPerform('publish');
        });
    }
}
