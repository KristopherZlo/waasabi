<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\ContentModerationService;
use App\Services\ScribbleAvatar;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('avatars:regenerate', function () {
    $outputDir = public_path('avatars');
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $count = 0;
    foreach (User::query()->get() as $user) {
        $name = $user->name ?? 'user';
        $svg = ScribbleAvatar::createSvgFromName($name);
        $filename = 'avatar-' . $user->id . '.svg';
        file_put_contents($outputDir . DIRECTORY_SEPARATOR . $filename, $svg);
        $user->avatar = '/avatars/' . $filename;
        $user->save();
        $count++;
    }

    $this->info('Regenerated ' . $count . ' avatars.');
})->purpose('Regenerate scribble avatars for all users');

Artisan::command('moderation:scan {path}', function (string $path) {
    $candidates = [];
    $trimmedPath = ltrim($path, '\\/');
    $candidates[] = $path;
    $candidates[] = base_path($path);
    $candidates[] = storage_path($path);
    $candidates[] = public_path($path);
    if (str_starts_with($trimmedPath, 'storage/')) {
        $relative = substr($trimmedPath, strlen('storage/'));
        $candidates[] = storage_path('app/public/' . $relative);
    }

    $resolved = null;
    foreach ($candidates as $candidate) {
        if ($candidate !== null && is_file($candidate)) {
            $resolved = $candidate;
            break;
        }
    }

    if (!$resolved) {
        $this->error('Image not found: ' . $path);
        return;
    }

    $service = app(ContentModerationService::class);
    $result = $service->scanImageForSexualContent($resolved);

    $this->info('Moderation scan result:');
    $this->line('status: ' . ($result['status'] ?? 'unknown'));
    $this->line('flagged: ' . ((bool) ($result['flagged'] ?? false) ? 'yes' : 'no'));
    if (!empty($result['reason'])) {
        $this->line('reason: ' . $result['reason']);
    }
    $labels = $result['labels'] ?? [];
    if (empty($labels)) {
        $this->line('labels: none');
        return;
    }

    $this->line('labels:');
    foreach ($labels as $label) {
        $name = (string) ($label['name'] ?? '');
        $parent = (string) ($label['parent'] ?? '');
        $confidence = $label['confidence'] ?? null;
        $text = $name !== '' ? $name : $parent;
        if ($parent !== '' && $parent !== $name) {
            $text = $parent . ' / ' . $name;
        }
        if (is_numeric($confidence)) {
            $text .= ' (' . number_format((float) $confidence, 1) . '%)';
        }
        $this->line('- ' . $text);
    }
})->purpose('Scan a local image with Rekognition moderation labels');

Artisan::command('notifications:test {account} {type} {text} {--link=}', function (string $account, string $type, string $text) {
    $account = trim($account);
    $type = trim($type);
    $text = trim($text);
    $link = trim((string) $this->option('link'));

    if ($account === '') {
        $this->error('Account identifier is required.');
        return;
    }
    if ($type === '') {
        $this->error('Notification type is required.');
        return;
    }
    if (strlen($type) > 60) {
        $this->error('Notification type must be 60 characters or less.');
        return;
    }
    if ($text === '') {
        $this->error('Notification text is required.');
        return;
    }
    if ($link !== '' && strlen($link) > 255) {
        $this->error('Link must be 255 characters or less.');
        return;
    }

    if (!Schema::hasTable('users')) {
        $this->error('Users table is unavailable.');
        return;
    }
    if (!Schema::hasTable('user_notifications')) {
        $this->error('Notifications table is unavailable.');
        return;
    }

    $user = null;
    if (ctype_digit($account)) {
        $user = User::query()->where('id', (int) $account)->first();
    } elseif (filter_var($account, FILTER_VALIDATE_EMAIL)) {
        $user = User::query()->where('email', $account)->first();
    } else {
        $slug = ltrim($account, '@');
        $user = User::query()->where('slug', $slug)->first();
    }

    if (!$user) {
        $this->error('User not found for account: ' . $account);
        return;
    }

    $notification = $user->sendNotification($type, $text, $link !== '' ? $link : null);
    if (!$notification) {
        $this->error('Notification was not created.');
        return;
    }

    if (Schema::hasTable('audit_logs')) {
        AuditLog::create([
            'user_id' => null,
            'event' => 'cli.notification.test',
            'target_type' => 'user',
            'target_id' => (string) $user->id,
            'ip_address' => null,
            'user_agent' => null,
            'meta' => [
                'account' => $account,
                'type' => $type,
                'text' => $text,
                'link' => $link !== '' ? $link : null,
            ],
        ]);
    }

    $this->info('Notification #' . $notification->id . ' sent to user #' . $user->id . '.');
})->purpose('Send a test notification to a specific user');
