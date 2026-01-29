<?php

use App\Models\AuditLog;
use App\Models\ContentReport;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\SupportTicket;
use App\Models\TopbarPromo;
use App\Models\User;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\ProfileSettingsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Services\AutoModerationService;
use App\Services\BadgeCatalogService;
use App\Services\ContentModerationService;
use App\Services\FeedService;
use App\Services\MakerPromotionService;
use App\Services\TextModerationService;
use App\Services\UserSlugService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

if (!function_exists('safeHasTable')) {
    function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('safeHasColumn')) {
    function safeHasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('logAuditEvent')) {
    function logAuditEvent(Request $request, string $event, ?User $actor = null, array $meta = [], ?string $targetType = null, ?string $targetId = null): void
    {
        if (!safeHasTable('audit_logs')) {
            return;
        }

        $userId = $actor?->id ?? $request->user()?->id;
        $userAgent = substr((string) $request->userAgent(), 0, 255);

        AuditLog::create([
            'user_id' => $userId,
            'event' => $event,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'meta' => $meta !== [] ? $meta : null,
        ]);
    }
}

if (!function_exists('honeypotTripped')) {
    function honeypotTripped(Request $request): bool
    {
        $value = (string) $request->input('website', '');
        return trim($value) !== '';
    }
}

if (!function_exists('captchaEnabled')) {
    function captchaEnabled(string $action): bool
    {
        $config = (array) config('waasabi.captcha', []);
        if (!(bool) ($config['enabled'] ?? false)) {
            return false;
        }
        $siteKey = trim((string) ($config['site_key'] ?? ''));
        $secret = trim((string) ($config['secret'] ?? ''));
        if ($siteKey === '' || $secret === '') {
            return false;
        }
        $actions = (array) ($config['actions'] ?? []);
        return (bool) ($actions[$action] ?? false);
    }
}

if (!function_exists('verifyCaptcha')) {
    function verifyCaptcha(Request $request): bool
    {
        $config = (array) config('waasabi.captcha', []);
        $provider = strtolower((string) ($config['provider'] ?? 'turnstile'));
        $secret = (string) ($config['secret'] ?? '');
        $token = (string) $request->input('cf-turnstile-response', '');

        if ($provider !== 'turnstile') {
            return true;
        }
        if ($secret === '' || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(4)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (\Throwable $e) {
            return false;
        }

        $payload = $response->json();
        return (bool) ($payload['success'] ?? false);
    }
}

if (!function_exists('supportStaffUsers')) {
    function supportStaffUsers()
    {
        if (!safeHasTable('users')) {
            return collect();
        }
        $roles = ['support', 'moderator', 'admin'];
        $query = User::query()->whereIn('role', $roles);
        if (safeHasColumn('users', 'is_banned')) {
            $query->where('is_banned', false);
        }
        return $query->get();
    }
}

if (!function_exists('notifySupportStaff')) {
    function notifySupportStaff(string $type, string $text, ?string $link = null, ?int $excludeUserId = null): void
    {
        if (!safeHasTable('user_notifications')) {
            return;
        }

        $staffUsers = supportStaffUsers();
        foreach ($staffUsers as $staff) {
            if ($excludeUserId && $staff->id === $excludeUserId) {
                continue;
            }
            $staff->sendNotification($type, $text, $link);
        }
    }
}

$badgeCatalog = app(BadgeCatalogService::class)->all();

if (!function_exists('badgePayload')) {
    function badgePayload(\App\Models\UserBadge $badge, array $catalogMap): array
    {
        $catalog = $catalogMap[$badge->badge_key] ?? null;
        $defaultName = $catalog['name'] ?? Str::title(str_replace('_', ' ', (string) $badge->badge_key));
        $defaultDescription = $catalog['description'] ?? '';
        $iconPath = $catalog['icon'] ?? '';
        $issuedAt = $badge->issued_at ? $badge->issued_at->format('Y-m-d') : '';

        return [
            'id' => $badge->id,
            'key' => $badge->badge_key,
            'label' => $badge->badge_name ?: $defaultName,
            'description' => $badge->badge_description ?: $defaultDescription,
            'reason' => $badge->reason ?? '',
            'issued_at' => $issuedAt,
            'icon' => $iconPath !== '' ? asset(ltrim($iconPath, '/')) : '',
        ];
    }
}

if (!function_exists('userBadgesPayload')) {
    function userBadgesPayload(User $user, array $badgeCatalog): array
    {
        if (!safeHasTable('user_badges')) {
            return [];
        }

        $catalogMap = collect($badgeCatalog)->keyBy('key')->all();

        return $user->badges()
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get()
            ->map(static fn($badge) => badgePayload($badge, $catalogMap))
            ->values()
            ->all();
    }
}

if (!function_exists('isModeratorRole')) {
    function isModeratorRole(?User $user): bool
    {
        return $user ? $user->hasRole('moderator') : false;
    }
}

if (!function_exists('applyVisibilityFilters')) {
    function applyVisibilityFilters($query, string $table, ?User $viewer): void
    {
        if ($table === 'posts' && safeHasTable('users') && safeHasColumn('users', 'is_banned')) {
            $query->whereNotIn($table . '.user_id', function ($sub) {
                $sub->select('id')
                    ->from('users')
                    ->where('is_banned', true);
            });
        }
        if (isModeratorRole($viewer)) {
            return;
        }
        if (safeHasColumn($table, 'is_hidden')) {
            $query->where($table . '.is_hidden', false);
        }
        if (safeHasColumn($table, 'moderation_status')) {
            $query->where($table . '.moderation_status', 'approved');
        }
    }
}

if (!function_exists('canViewHiddenContent')) {
    function canViewHiddenContent(?User $viewer, ?int $ownerId = null): bool
    {
        if (isModeratorRole($viewer)) {
            return true;
        }
        return $viewer && $ownerId && $viewer->id === $ownerId;
    }
}





if (!function_exists('getTopbarPromos')) {
    function getTopbarPromos(bool $onlyActive = true): array
    {
        if (!safeHasTable('topbar_promos')) {
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
}

if (!function_exists('pickTopbarPromo')) {
    function pickTopbarPromo(): ?array
    {
        if (!safeHasTable('topbar_promos')) {
            return null;
        }
        $hasStartsAt = safeHasColumn('topbar_promos', 'starts_at');
        $hasEndsAt = safeHasColumn('topbar_promos', 'ends_at');
        $hasMaxImpressions = safeHasColumn('topbar_promos', 'max_impressions');
        $hasImpressionsCount = safeHasColumn('topbar_promos', 'impressions_count');

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
}

if (!function_exists('createImageResource')) {
    function createImageResource(string $path, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };
    }
}

if (!function_exists('resizeImageResource')) {
    function resizeImageResource($source, int $sourceWidth, int $sourceHeight, int $targetWidth, int $targetHeight, bool $preserveAlpha)
    {
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($preserveAlpha) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        } else {
            $white = imagecolorallocate($target, 255, 255, 255);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $white);
        }
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        return $target;
    }
}

if (!function_exists('cropImageResource')) {
    function cropImageResource($source, int $x, int $y, int $cropWidth, int $cropHeight, bool $preserveAlpha)
    {
        $target = imagecreatetruecolor($cropWidth, $cropHeight);
        if ($preserveAlpha) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $cropWidth, $cropHeight, $transparent);
        } else {
            $white = imagecolorallocate($target, 255, 255, 255);
            imagefilledrectangle($target, 0, 0, $cropWidth, $cropHeight, $white);
        }
        imagecopy($target, $source, 0, 0, $x, $y, $cropWidth, $cropHeight);
        return $target;
    }
}

if (!function_exists('saveImageResource')) {
    function saveImageResource($image, string $path, string $format, int $quality): bool
    {
        if ($format === 'webp') {
            if (!function_exists('imagewebp')) {
                return false;
            }
            return imagewebp($image, $path, $quality);
        }
        if ($format === 'jpeg') {
            return imagejpeg($image, $path, $quality);
        }
        return false;
    }
}

if (!function_exists('processImageUpload')) {
    function processImageUpload(UploadedFile $file, array $options): array
    {
        $path = $file->getRealPath();
        if (!$path) {
            throw new RuntimeException('Invalid upload.');
        }

        $info = @getimagesize($path);
        if (!$info) {
            throw new RuntimeException('Invalid image.');
        }

        [$width, $height, $type] = $info;
        $allowedTypes = $options['allowed_types'] ?? [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
        if (!in_array($type, $allowedTypes, true)) {
            throw new RuntimeException('Unsupported image type.');
        }

        $minWidth = $options['min_width'] ?? null;
        if ($minWidth && $width < $minWidth) {
            throw new RuntimeException('Image too small.');
        }
        $minHeight = $options['min_height'] ?? null;
        if ($minHeight && $height < $minHeight) {
            throw new RuntimeException('Image too small.');
        }

        $maxWidth = $options['max_width'] ?? null;
        if ($maxWidth && $width > $maxWidth) {
            throw new RuntimeException('Image too large.');
        }
        $maxHeight = $options['max_height'] ?? null;
        if ($maxHeight && $height > $maxHeight) {
            throw new RuntimeException('Image too large.');
        }

        $minSide = $options['min_side'] ?? null;
        if ($minSide && min($width, $height) < $minSide) {
            throw new RuntimeException('Image too small.');
        }

        $maxSideInput = $options['max_side_input'] ?? null;
        if ($maxSideInput && max($width, $height) > $maxSideInput) {
            throw new RuntimeException('Image too large.');
        }

        $maxPixels = $options['max_pixels'] ?? null;
        if ($maxPixels && ($width * $height) > $maxPixels) {
            throw new RuntimeException('Image too large.');
        }

        $targetWidthOption = $options['target_width'] ?? null;
        $targetHeightOption = $options['target_height'] ?? null;

        $cropAspectOption = $options['crop_aspect'] ?? null;
        if (!$cropAspectOption && $targetWidthOption && $targetHeightOption) {
            $cropAspectOption = (float) $targetWidthOption / max(1, (float) $targetHeightOption);
        }
        $cropAspect = is_numeric($cropAspectOption) ? max(0.1, (float) $cropAspectOption) : null;

        $cropX = 0;
        $cropY = 0;
        $cropWidth = $width;
        $cropHeight = $height;
        if ($cropAspect) {
            $currentAspect = $width / max(1, $height);
            if ($currentAspect > $cropAspect) {
                $cropWidth = max(1, (int) floor($height * $cropAspect));
                $cropX = (int) floor(($width - $cropWidth) / 2);
            } elseif ($currentAspect < $cropAspect) {
                $cropHeight = max(1, (int) floor($width / $cropAspect));
                $cropY = (int) floor(($height - $cropHeight) / 2);
            }
        }

        $workingWidth = $cropWidth;
        $workingHeight = $cropHeight;

        if ($targetWidthOption && $targetHeightOption) {
            $targetWidth = max(1, (int) $targetWidthOption);
            $targetHeight = max(1, (int) $targetHeightOption);
        } else {
            $maxSide = $options['max_side'] ?? null;
            $scale = $maxSide ? min(1, $maxSide / max($workingWidth, $workingHeight)) : 1;
            $targetWidth = max(1, (int) round($workingWidth * $scale));
            $targetHeight = max(1, (int) round($workingHeight * $scale));
        }

        $format = $options['format'] ?? 'webp';
        if ($format === 'webp' && !function_exists('imagewebp')) {
            $format = 'jpeg';
        }
        $extension = $format === 'webp' ? 'webp' : 'jpg';
        $quality = (int) ($options['quality'] ?? 82);
        $preserveAlpha = $format === 'webp';

        $dir = trim((string) ($options['dir'] ?? 'uploads'), '/');
        $previewDir = $options['preview_dir'] ?? null;
        $previewDir = $previewDir ? trim((string) $previewDir, '/') : null;
        $disk = Storage::disk('public');
        $disk->makeDirectory($dir);
        if ($previewDir) {
            $disk->makeDirectory($previewDir);
        }

        $basename = (string) Str::uuid();
        $relativePath = $dir . '/' . $basename . '.' . $extension;
        $fullPath = storage_path('app/public/' . $relativePath);

        $source = createImageResource($path, $type);
        if (!$source) {
            throw new RuntimeException('Unable to read image.');
        }

        $workingSource = $source;
        if ($cropAspect) {
            $workingSource = cropImageResource($source, $cropX, $cropY, $cropWidth, $cropHeight, $preserveAlpha);
            if (!$workingSource) {
                imagedestroy($source);
                throw new RuntimeException('Unable to crop image.');
            }
        }

        $resized = resizeImageResource($workingSource, $workingWidth, $workingHeight, $targetWidth, $targetHeight, $preserveAlpha);
        if (!saveImageResource($resized, $fullPath, $format, $quality)) {
            imagedestroy($source);
            if ($workingSource !== $source) {
                imagedestroy($workingSource);
            }
            imagedestroy($resized);
            throw new RuntimeException('Unable to save image.');
        }

        $previewPath = null;
        if ($previewDir && !empty($options['preview_side'])) {
            $previewSide = (int) $options['preview_side'];
            $previewScale = min(1, $previewSide / max($workingWidth, $workingHeight));
            $previewWidth = max(1, (int) round($workingWidth * $previewScale));
            $previewHeight = max(1, (int) round($workingHeight * $previewScale));
            $previewRelative = $previewDir . '/' . $basename . '.' . $extension;
            $previewFull = storage_path('app/public/' . $previewRelative);
            $previewImage = resizeImageResource($workingSource, $workingWidth, $workingHeight, $previewWidth, $previewHeight, $preserveAlpha);
            if (saveImageResource($previewImage, $previewFull, $format, $quality)) {
                $previewPath = 'storage/' . $previewRelative;
            }
            imagedestroy($previewImage);
        }

        if ($workingSource !== $source) {
            imagedestroy($workingSource);
        }
        imagedestroy($source);
        imagedestroy($resized);

        return [
            'path' => 'storage/' . $relativePath,
            'preview' => $previewPath,
        ];
    }
}

if (!function_exists('resolveModerationStoragePath')) {
    function resolveModerationStoragePath(string $publicPath): ?string
    {
        $path = ltrim($publicPath, '/');
        if (!Str::startsWith($path, 'storage/')) {
            return null;
        }
        $relative = Str::after($path, 'storage/');
        return storage_path('app/public/' . $relative);
    }
}

if (!function_exists('isUserUploadedMediaPath')) {
    function isUserUploadedMediaPath(string $publicPath): bool
    {
        $path = ltrim($publicPath, '/');
        if (!Str::startsWith($path, 'storage/')) {
            return false;
        }
        $relative = Str::after($path, 'storage/');
        return Str::startsWith($relative, 'uploads/');
    }
}

if (!function_exists('formatModerationDetails')) {
    function formatModerationDetails(array $labels, string $context): string
    {
        $parts = [];
        foreach ($labels as $label) {
            $name = (string) ($label['name'] ?? '');
            $parent = (string) ($label['parent'] ?? '');
            $confidence = $label['confidence'] ?? null;
            if ($name === '' && $parent === '') {
                continue;
            }
            $labelText = $name;
            if ($parent !== '' && $parent !== $name) {
                $labelText = $parent . ' / ' . $name;
            }
            if (is_numeric($confidence)) {
                $labelText .= ' ' . number_format((float) $confidence, 1) . '%';
            }
            $parts[] = $labelText;
        }
        $prefix = $context !== '' ? ('Rekognition (' . $context . ')') : 'Rekognition';
        $body = implode('; ', $parts);
        return $body !== '' ? ($prefix . ': ' . $body) : $prefix;
    }
}

if (!function_exists('formatModerationFallbackDetails')) {
    function formatModerationFallbackDetails(?string $reason, string $context): string
    {
        $reason = $reason ? str_replace('_', ' ', $reason) : 'unknown';
        $prefix = $context !== '' ? ('Rekognition (' . $context . ')') : 'Rekognition';
        return $prefix . ' unavailable: ' . $reason;
    }
}

if (!function_exists('maybeFlagImageForModeration')) {
    function maybeFlagImageForModeration(string $publicPath, ?User $user, string $context): array
    {
        $result = [
            'status' => 'skipped',
            'flagged' => false,
            'labels' => [],
            'reason' => null,
        ];

        if (!config('services.rekognition.enabled')) {
            $result['reason'] = 'disabled';
            return $result;
        }

        $fallbackAction = (string) config('services.rekognition.fallback_action', 'mod');
        $fallbackAction = in_array($fallbackAction, ['post', 'nsfw', 'mod'], true) ? $fallbackAction : 'mod';

        $normalized = ltrim($publicPath, '/');
        if (Str::startsWith($normalized, 'storage/')) {
            $publicPath = 'storage/' . Str::after($normalized, 'storage/');
        } else {
            $publicPath = $normalized;
        }

        if ($user && $user->isAdmin()) {
            $result['reason'] = 'admin_skip';
            return $result;
        }

        if (!isUserUploadedMediaPath($publicPath)) {
            $result['reason'] = 'not_user_upload';
            return $result;
        }

        $absolutePath = resolveModerationStoragePath($publicPath);
        if ($absolutePath === null) {
            $result['status'] = 'error';
            $result['reason'] = 'path_unresolvable';
            return $result;
        }

        try {
            $service = app(ContentModerationService::class);
            $scan = $service->scanImageForSexualContent($absolutePath);
        } catch (\Throwable $exception) {
            Log::warning('Moderation scan failed.', ['error' => $exception->getMessage()]);
            $result['status'] = 'error';
            $result['reason'] = 'scan_exception';
            return $result;
        }

        if (is_array($scan)) {
            $result = array_merge($result, $scan);
        }

        if (!safeHasTable('content_reports')) {
            return $result;
        }

        $status = (string) ($result['status'] ?? '');
        $labels = $result['labels'] ?? [];
        $shouldLog = !empty($labels) || ($fallbackAction === 'mod' && $status !== 'ok');

        if (!$shouldLog) {
            return $result;
        }

        $alreadyLogged = ContentReport::query()
            ->where('content_type', 'content')
            ->where('content_url', $publicPath)
            ->where('reason', 'admin_flag')
            ->exists();

        if ($alreadyLogged) {
            return $result;
        }

        $details = !empty($labels)
            ? formatModerationDetails($labels, $context)
            : formatModerationFallbackDetails($result['reason'] ?? null, $context);

        ContentReport::create([
            'user_id' => $user?->id,
            'content_type' => 'content',
            'content_id' => null,
            'content_url' => $publicPath,
            'reason' => 'admin_flag',
            'details' => $details,
        ]);

        return $result;
    }
}

if (!function_exists('extractUserUploadedImagePathsFromHtml')) {
    function extractUserUploadedImagePathsFromHtml(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $matches = [];
        preg_match_all('/<img[^>]+src=["\\\']([^"\\\']+)["\\\']/i', $html, $matches);
        $sources = $matches[1] ?? [];
        if (empty($sources)) {
            return [];
        }

        $paths = [];
        foreach ($sources as $source) {
            $source = trim((string) $source);
            if ($source === '') {
                continue;
            }
            $path = parse_url($source, PHP_URL_PATH);
            $path = is_string($path) && $path !== '' ? $path : $source;
            $normalized = $path;
            $storagePos = strpos($normalized, '/storage/');
            if ($storagePos !== false) {
                $normalized = substr($normalized, $storagePos + 1);
            } else {
                $normalized = ltrim($normalized, '/');
            }
            if (!Str::startsWith($normalized, 'storage/')) {
                continue;
            }
            $paths[] = $normalized;
        }

        return array_values(array_unique($paths));
    }
}

if (!function_exists('resolveModerationLocation')) {
    function resolveModerationLocation(Request $request): ?string
    {
        $country = $request->header('CF-IPCountry')
            ?? $request->header('X-Geo-Country')
            ?? $request->header('X-Appengine-Country')
            ?? $request->header('X-Country');
        $country = is_string($country) ? trim($country) : null;
        if ($country === '' || $country === 'XX') {
            $country = null;
        }

        $region = $request->header('X-Geo-Region') ?? $request->header('X-Region');
        $region = is_string($region) ? trim($region) : null;
        if ($region === '') {
            $region = null;
        }

        if ($country && $region) {
            return $country . '-' . $region;
        }

        return $country ?: $region;
    }
}

if (!function_exists('logModerationAction')) {
    function logModerationAction(Request $request, User $moderator, string $action, string $contentType, ?string $contentId, ?string $contentUrl, ?string $notes = null, array $meta = []): void
    {
        if (!safeHasTable('moderation_logs')) {
            return;
        }

        if (!isModeratorRole($moderator)) {
            return;
        }

        ModerationLog::create([
            'moderator_id' => $moderator->id,
            'moderator_name' => $moderator->name ?? 'moderator',
            'moderator_role' => $moderator->role ?? 'moderator',
            'action' => $action,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'content_url' => $contentUrl,
            'notes' => $notes,
            'ip_address' => $request->ip(),
            'location' => resolveModerationLocation($request),
            'user_agent' => $request->userAgent(),
            'meta' => $meta,
        ]);
    }
}

if (!function_exists('resolvePostUrl')) {
    function resolvePostUrl(string $slug): string
    {
        if (safeHasTable('posts')) {
            $post = Post::where('slug', $slug)->first();
            if ($post?->type === 'question') {
                return route('questions.show', $slug);
            }
        }
        return route('project', $slug);
    }
}

if (!function_exists('shouldBlockModeration')) {
    function shouldBlockModeration(User $actor, ?User $owner): bool
    {
        return $owner !== null && $actor->roleKey() === 'moderator' && $owner->isAdmin();
    }
}

if (!function_exists('setModerationState')) {
    function setModerationState($model, ?User $actor, string $status): void
    {
        $isHidden = $status !== 'approved';
        $model->is_hidden = $isHidden;
        $model->moderation_status = $status;
        $model->hidden_at = $isHidden ? now() : null;
        $model->hidden_by = $isHidden ? ($actor?->id) : null;
        $model->save();
    }
}

$generateUserSlug = static function (string $name): string {
    return app(UserSlugService::class)->generate($name);
};

$preparePostStats = static function (iterable $posts): array {
    return FeedService::preparePostStats($posts, Auth::user());
};

$projects = [
    [
        'slug' => 'power-hub-night',
        'title' => 'Power module for a field hub',
        'subtitle' => 'Night build: stabilized noise and heat without extra parts.',
        'context' => 'fought power noise and finally stabilized the rail',
        'published' => '1 hour ago',
        'published_minutes' => 60,
        'score' => 128,
        'returns' => 14,
        'saves' => 5,
        'read_time' => '10 min',
        'read_time_minutes' => 10,
        'cover' => '/images/cover-gradient.svg',
        'media' => 'media--pulse',
        'status_key' => 'done',
        'status' => __('ui.project.status_done'),
        'tags' => ['hardware', 'power', 'night build'],
        'author' => ['name' => 'Dasha N.', 'role' => 'maker', 'avatar' => '/images/avatar-1.svg'],
        'comments' => [
            ['author' => 'Ira P.', 'time' => '2 hours ago', 'section' => 'Context and constraints', 'text' => 'Super relatable. Which regulator did you settle on?', 'useful' => 5, 'role' => 'user'],
            ['author' => 'Misha G.', 'time' => 'yesterday', 'section' => 'Measurements', 'text' => 'Same pain here. Thanks for a clean write-up.', 'useful' => 3, 'role' => 'maker'],
        ],
        'reviews' => [
            [
                'author' => ['name' => 'Ilya M.', 'role' => 'Maker', 'avatar' => '/images/avatar-4.svg', 'note' => '12 reviews in electronics'],
                'time' => '1 hour ago',
                'improve' => 'Add a short table comparing the three variants with noise and heat metrics.',
                'why' => 'Readers can compare at a glance without scanning paragraphs.',
                'how' => 'One table after “Measurements and fixes” with 3 rows and 3 columns.',
            ],
        ],
        'reviewer' => ['name' => 'Ilya M.', 'role' => 'Maker', 'note' => '12 reviews in electronics', 'avatar' => '/images/avatar-4.svg'],
        'sections' => [
            [
                'title' => 'Context and constraints',
                'blocks' => [
                    ['type' => 'p', 'text' => 'This module powers a small field hub: sensors, telemetry, and a tiny compute board. Weight and heat were the main constraints, and the board had to run from a single battery pack.'],
                    ['type' => 'p', 'text' => 'In the field the supply is noisy: voltage dips, bursts, and ripple. On the first run the sensors drifted and the readings were useless.'],
                    ['type' => 'note', 'text' => 'Goal for iteration one: stable 5V and predictable heat on the enclosure.'],
                    ['type' => 'quote', 'text' => 'Without a stable rail, everything else is just noise.'],
                    ['type' => 'p', 'text' => 'So I decided to fix power first and postpone everything else. It was painful to skip features, but it saved the build.'],
                ],
            ],
            [
                'title' => 'What I changed and why',
                'blocks' => [
                    ['type' => 'p', 'text' => 'I built three variants and logged noise, heat, and startup behavior. It was obvious that the layout was as important as the parts.'],
                    ['type' => 'h3', 'text' => 'Filtering'],
                    ['type' => 'p', 'text' => 'A small LC filter at the input and a strict split between dirty and clean ground helped more than expected. Short traces mattered.'],
                    ['type' => 'p', 'text' => 'I separated sensitive loads into their own branch and moved the regulator away from the sensor headers.'],
                    ['type' => 'note', 'text' => 'Every change went into a small measurement table to avoid guesswork.'],
                    ['type' => 'image', 'caption' => 'Measurement rig and three module variants.', 'src' => '/images/cover-gradient.svg'],
                ],
            ],
            [
                'title' => 'Measurements and fixes',
                'blocks' => [
                    ['type' => 'p', 'text' => 'A ground loop showed up only when sensors started together. Removing a single jumper reduced the spike by half.'],
                    ['type' => 'p', 'text' => 'After the fix the noise floor dropped and the readings stabilized within seconds.'],
                    ['type' => 'quote', 'text' => 'One jumper cost an hour, but saved the week.'],
                ],
            ],
            [
                'title' => 'Results and next step',
                'blocks' => [
                    ['type' => 'p', 'text' => 'The module now holds 5V with predictable heat and no sensor drift. It runs six hours on a pack without thermal runaway.'],
                    ['type' => 'p', 'text' => 'Next: lighter revision and reverse polarity protection.'],
                    ['type' => 'note', 'text' => 'If you have a similar setup, share your fix. I am collecting comparisons.'],
                ],
            ],
        ],
    ],
    [
        'slug' => 'weekend-gesture-board',
        'title' => 'Weekend gesture panel prototype',
        'subtitle' => 'Two days to test gesture control with real users.',
        'context' => 'built a fast gesture board to validate the interaction',
        'published' => '3 hours ago',
        'published_minutes' => 180,
        'score' => 97,
        'returns' => 9,
        'saves' => 4,
        'read_time' => '8 min',
        'read_time_minutes' => 8,
        'cover' => '/images/cover-gradient.svg',
        'media' => 'media--wire',
        'status_key' => 'done',
        'status' => __('ui.project.status_done'),
        'tags' => ['prototype', 'ux', 'sensors'],
        'author' => ['name' => 'Timur K.', 'role' => 'user', 'avatar' => '/images/avatar-2.svg'],
        'comments' => [
            ['author' => 'Nastya V.', 'time' => '3 hours ago', 'section' => 'Weekend build', 'text' => 'Looks great. Can you share the wiring diagram?', 'useful' => 2, 'role' => 'user'],
            ['author' => 'Petya D.', 'time' => 'yesterday', 'section' => 'Calibration and UX', 'text' => 'Any idea how to reduce false positives?', 'useful' => 1, 'role' => 'user'],
        ],
        'reviews' => [
            [
                'author' => ['name' => 'Sveta L.', 'role' => 'Maker', 'avatar' => '/images/avatar-5.svg', 'note' => '6 gesture prototypes shipped'],
                'time' => '2 hours ago',
                'improve' => 'Show two failure cases so readers see the edge of the sensor field.',
                'why' => 'People copy the happy path and then get surprised by noise.',
                'how' => 'Add a short clip or two photos with notes under "Calibration and UX".',
                'useful' => 4,
            ],
            [
                'author' => ['name' => 'Ilya M.', 'role' => 'Maker', 'avatar' => '/images/avatar-4.svg', 'note' => '12 reviews in electronics'],
                'time' => 'yesterday',
                'improve' => 'Add a quick calibration checklist for the two gestures.',
                'why' => 'It makes the prototype reproducible for other teams.',
                'how' => 'A 4-step list with timer values and distance hints.',
                'useful' => 2,
            ],
        ],
        'reviewer' => ['name' => 'Sveta L.', 'role' => 'Maker', 'note' => '6 gesture prototypes shipped', 'avatar' => '/images/avatar-5.svg'],
        'sections' => [
            [
                'title' => 'Why gestures and why fast',
                'blocks' => [
                    ['type' => 'p', 'text' => 'I wanted touchless control with no menus. The fastest way was a two-day prototype with real users.'],
                    ['type' => 'p', 'text' => 'If it failed in a weekend, I would not waste months. The goal was signal, not polish.'],
                    ['type' => 'quote', 'text' => 'If we cannot make it usable in two days, it is not the right direction.'],
                ],
            ],
            [
                'title' => 'Build and setup',
                'blocks' => [
                    ['type' => 'p', 'text' => 'I mounted the sensors in a simple frame and paired them with a tablet UI for quick feedback.'],
                    ['type' => 'p', 'text' => 'The layout was rough, but it kept wiring short and noise low.'],
                    ['type' => 'note', 'text' => 'Assembly speed was more important than aesthetics.'],
                    ['type' => 'image', 'caption' => 'Prototype panel and wiring.', 'src' => '/images/cover-gradient.svg'],
                ],
            ],
            [
                'title' => 'Calibration and UX',
                'blocks' => [
                    ['type' => 'p', 'text' => 'False triggers were the main issue. I reduced gesture space and kept only two actions.'],
                    ['type' => 'h3', 'text' => 'Feedback loop'],
                    ['type' => 'p', 'text' => 'A simple visual confirmation made users trust the system. Without it, they repeated gestures and caused errors.'],
                ],
            ],
            [
                'title' => 'Takeaways',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Gestures work if the system is strict and explicit. Ambiguity kills confidence.'],
                    ['type' => 'p', 'text' => 'Next I will try haptics and add a quick calibration step.'],
                ],
            ],
        ],
    ],
    [
        'slug' => 'field-notes',
        'title' => 'Field notes feed for a student team',
        'subtitle' => 'A calm reading flow so people can return without pressure.',
        'context' => 'built a feed to replace scattered chat logs',
        'published' => 'yesterday',
        'published_minutes' => 1440,
        'score' => 81,
        'returns' => 11,
        'saves' => 9,
        'read_time' => '6 min',
        'read_time_minutes' => 6,
        'cover' => '/images/cover-gradient.svg',
        'media' => 'media--grid',
        'status_key' => 'in-progress',
        'status' => __('ui.project.status_in_progress'),
        'tags' => ['writing', 'product', 'ux'],
        'author' => ['name' => 'Morgana O.', 'role' => 'user', 'avatar' => '/images/avatar-3.svg'],
        'comments' => [
            ['author' => 'Alina S.', 'time' => 'yesterday', 'section' => 'Reading flow', 'text' => 'The table of contents is exactly what we missed.', 'useful' => 4, 'role' => 'maker'],
            ['author' => 'Roma I.', 'time' => 'yesterday', 'section' => 'Return signals', 'text' => 'Bookmark + progress is a strong combo.', 'useful' => 2, 'role' => 'user'],
        ],
        'reviews' => [
            [
                'author' => ['name' => 'Mila T.', 'role' => 'Maker', 'avatar' => '/images/avatar-6.svg', 'note' => 'Content systems, 9 projects'],
                'time' => 'yesterday',
                'improve' => 'Add a simple "last read" stamp in the feed cards.',
                'why' => 'It reinforces the return habit and helps users pick where to resume.',
                'how' => 'Show a subtle line under the title with the last open time.',
                'useful' => 5,
            ],
            [
                'author' => ['name' => 'Alina S.', 'role' => 'Maker', 'avatar' => '/images/avatar-3.svg', 'note' => 'Reading flow researcher'],
                'time' => '2 days ago',
                'improve' => 'Clarify the difference between save and return signals.',
                'why' => 'Users might assume they do the same thing.',
                'how' => 'Add a short paragraph in "Return signals" with a concrete example.',
                'useful' => 3,
            ],
            [
                'author' => ['name' => 'Roma I.', 'role' => 'User', 'avatar' => '/images/avatar-2.svg', 'note' => 'Student team lead'],
                'time' => '3 days ago',
                'improve' => 'Provide a template for note entries to keep them consistent.',
                'why' => 'It will keep quality stable as the team grows.',
                'how' => 'Offer a short 3-field template in the publish flow.',
                'useful' => 1,
            ],
        ],
        'reviewer' => ['name' => 'Mila T.', 'role' => 'Maker', 'note' => 'Content systems, 9 projects', 'avatar' => '/images/avatar-6.svg'],
        'sections' => [
            [
                'title' => 'Why the feed',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Our team lived in chats. Messages expired after a day and context disappeared. We needed a calmer archive.'],
                    ['type' => 'p', 'text' => 'A feed is not chat. It stores context, lets you pause, and return without pressure.'],
                    ['type' => 'quote', 'text' => 'Reading should feel calm, not noisy.'],
                ],
            ],
            [
                'title' => 'How reading works',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Every note is a short block: context, action, question. Longer details live below.'],
                    ['type' => 'p', 'text' => 'Discussion is separated so it does not break the reading flow.'],
                    ['type' => 'h3', 'text' => 'Visual pauses'],
                    ['type' => 'p', 'text' => 'Quotes and callouts create breathing space in long text.'],
                    ['type' => 'note', 'text' => 'If text can be read in slices, people actually finish it.'],
                    ['type' => 'image', 'caption' => 'Example of a quiet reading layout.', 'src' => '/images/cover-gradient.svg'],
                ],
            ],
            [
                'title' => 'Return signals',
                'blocks' => [
                    ['type' => 'p', 'text' => 'I track returns, saves, and inline reactions, not raw clicks.'],
                    ['type' => 'p', 'text' => 'Saving is stronger than a like. Returning a day later is even stronger.'],
                    ['type' => 'h3', 'text' => 'Why it matters'],
                    ['type' => 'p', 'text' => 'If someone returns, the text stays in their head. That is the real signal.'],
                ],
            ],
            [
                'title' => 'Next steps',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Add roles, project filters, and weight reactions by experience.'],
                    ['type' => 'p', 'text' => 'Ship a private read-later library with auto-resume.'],
                    ['type' => 'h3', 'text' => 'Minimal friction'],
                    ['type' => 'p', 'text' => 'Keep only reading and support. Everything else is optional.'],
                ],
            ],
        ],
    ],
    [
        'slug' => 'fast-breakdown',
        'title' => 'Fast project reviews system',
        'subtitle' => 'A format that forces clarity in under 3 minutes.',
        'context' => 'trying to make reviews short and useful',
        'published' => '4 days ago',
        'published_minutes' => 5760,
        'score' => 65,
        'returns' => 6,
        'saves' => 2,
        'read_time' => '9 min',
        'read_time_minutes' => 9,
        'cover' => '/images/cover-gradient.svg',
        'media' => 'media--pulse',
        'status_key' => 'paused',
        'status' => __('ui.project.status_paused'),
        'tags' => ['review', 'process', 'motivation'],
        'author' => ['name' => 'Nikita B.', 'role' => 'user', 'avatar' => '/images/avatar-7.svg'],
        'comments' => [
            ['author' => 'Oleg S.', 'time' => '6 hours ago', 'section' => '3-minute format', 'text' => 'Timers are a great constraint.', 'useful' => 3, 'role' => 'user'],
            ['author' => 'Katya F.', 'time' => 'yesterday', 'section' => 'Templates', 'text' => 'Cards format could work well here.', 'useful' => 1, 'role' => 'admin'],
        ],
        'reviews' => [
            [
                'author' => ['name' => 'Lena S.', 'role' => 'Maker', 'avatar' => '/images/avatar-8.svg', 'note' => '20 short reviews'],
                'time' => '3 days ago',
                'improve' => 'Add one real example review in the intro.',
                'why' => 'It will show the format faster than an explanation.',
                'how' => 'Place a 3-block card under "The pain of reviews".',
                'useful' => 6,
            ],
            [
                'author' => ['name' => 'Katya F.', 'role' => 'Admin', 'avatar' => '/images/avatar-7.svg', 'note' => 'Runs moderation'],
                'time' => '4 days ago',
                'improve' => 'Explain how you prevent low-effort answers.',
                'why' => 'Without guardrails people will write one-word replies.',
                'how' => 'Add a minimum word count and a sample response.',
                'useful' => 3,
            ],
            [
                'author' => ['name' => 'Oleg S.', 'role' => 'User', 'avatar' => '/images/avatar-1.svg', 'note' => 'Weekly reviewer'],
                'time' => 'last week',
                'improve' => 'Show how long a review usually takes in practice.',
                'why' => 'It sets expectations and increases adoption.',
                'how' => 'Add a small stat line: median 2.7 minutes.',
                'useful' => 2,
            ],
            [
                'author' => ['name' => 'Ira P.', 'role' => 'Maker', 'avatar' => '/images/avatar-4.svg', 'note' => 'Hardware reviews'],
                'time' => 'last week',
                'improve' => 'Include a variant for longer, complex projects.',
                'why' => 'Some projects need more context before feedback.',
                'how' => 'Offer an optional 4th block for constraints.',
                'useful' => 1,
            ],
        ],
        'reviewer' => ['name' => 'Lena S.', 'role' => 'Maker', 'note' => '20 short reviews', 'avatar' => '/images/avatar-8.svg'],
        'sections' => [
            [
                'title' => 'The pain of reviews',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Reviews tend to be long and heavy. People postpone them because they fear a long write-up.'],
                    ['type' => 'p', 'text' => 'I wanted a format that keeps flow and does not require mood.'],
                    ['type' => 'quote', 'text' => 'If a review cannot fit in 3 minutes, it will never happen.'],
                ],
            ],
            [
                'title' => 'Three blocks',
                'blocks' => [
                    ['type' => 'p', 'text' => 'The review is split into three blocks: strong side, improvement, question.'],
                    ['type' => 'p', 'text' => 'Each block is limited to two sentences. That keeps it readable.'],
                    ['type' => 'h3', 'text' => 'Template'],
                    ['type' => 'p', 'text' => 'The template keeps you from freezing. You just answer three questions.'],
                    ['type' => 'note', 'text' => 'Timers and hints help keep momentum.'],
                    ['type' => 'image', 'caption' => 'A quick review card example.', 'src' => '/images/cover-gradient.svg'],
                ],
            ],
            [
                'title' => 'Experiments',
                'blocks' => [
                    ['type' => 'p', 'text' => 'I tested several prompt styles, from strict to gentle. Strict prompts yielded more concrete feedback.'],
                    ['type' => 'p', 'text' => 'Showing reviewer experience helped trust, but needs validation.'],
                    ['type' => 'h3', 'text' => 'What worked'],
                    ['type' => 'p', 'text' => 'Timer plus example answer kept people moving.'],
                ],
            ],
            [
                'title' => 'Why I paused it',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Without automation the flow became manual and slow.'],
                    ['type' => 'p', 'text' => 'Next step is auto-prompts and a simple stats view.'],
                ],
            ],
        ],
    ],
];

$demoAuthors = [];
if (safeHasTable('users')) {
    $demoAuthors = User::query()
        ->orderBy('id')
        ->get()
        ->map(function (User $user) use ($generateUserSlug) {
            $name = $user->name ?? __('ui.project.anonymous');
            $slug = $user->slug ?? Str::slug($user->name ?? '');
            if ($slug === '') {
                $slug = $generateUserSlug($user->name ?? 'user');
            }
            return [
                'id' => $user->id,
                'name' => $name,
                'slug' => $slug,
                'role' => $user->role ?? 'user',
                'avatar' => $user->avatar ?? '/images/avatar-default.svg',
            ];
        })
        ->values()
        ->all();
}

$demoAuthorIndex = 0;
$nextDemoAuthor = static function () use (&$demoAuthorIndex, $demoAuthors) {
    if (!$demoAuthors) {
        return null;
    }
    $author = $demoAuthors[$demoAuthorIndex % count($demoAuthors)];
    $demoAuthorIndex += 1;
    return $author;
};

if (!empty($demoAuthors)) {
    $projects = array_map(static function (array $project) use ($nextDemoAuthor) {
        $author = $nextDemoAuthor();
        if ($author) {
            $project['author'] = $author;
        }
        return $project;
    }, $projects);
}

$profile = [
    'name' => 'Dasha N.',
    'slug' => 'dasha-n',
    'bio' => 'I build hardware prototypes and write concise post-mortems.',
    'role' => 'maker',
    'avatar' => '/images/avatar-1.svg',
    'quotes' => [
        '"I can see the work ? thanks for showing the process."',
        '"Great that you shipped it to a field test."',
    ],
];

$showcase = [
    ['title' => 'Shipped to the end', 'projects' => [$projects[0], $projects[1]]],
    ['title' => 'Living process', 'projects' => [$projects[2]]],
    ['title' => 'Strong reviews', 'projects' => [$projects[3]]],
];

$qa_questions = [
    [
        'slug' => 'read-time-metrics',
        'title' => 'How do you estimate read time for long posts?',
        'time' => '20:40',
        'published_minutes' => 40,
        'delta' => '+1',
        'tags' => ['writing', 'metrics'],
        'author' => ['name' => 'Ilya N.', 'role' => 'user', 'avatar' => '/images/avatar-2.svg'],
        'body' => "I have long project posts with code, tables, and diagrams.\n\nDo you use words-per-minute, or do you count code blocks and images separately? Looking for a simple rule of thumb.",
        'answers' => [
            [
                'author' => ['name' => 'Dasha N.', 'role' => 'maker', 'avatar' => '/images/avatar-1.svg'],
                'time' => '18 min ago',
                'text' => 'I start with 180 words/min and add ~30 seconds per figure/table. For code, I ignore it unless the block is long.',
                'score' => 18,
                'replies' => [
                    [
                        'author' => ['name' => 'Ilya N.', 'role' => 'user', 'avatar' => '/images/avatar-2.svg'],
                        'time' => '10 min ago',
                        'text' => 'Do you treat dense tables differently or same rule?',
                        'score' => 3,
                    ],
                    [
                        'author' => ['name' => 'Dasha N.', 'role' => 'maker', 'avatar' => '/images/avatar-1.svg'],
                        'time' => '6 min ago',
                        'text' => 'If it is more than a screen, I add 20-30 sec. Otherwise I keep it simple.',
                        'score' => 5,
                    ],
                ],
            ],
            [
                'author' => ['name' => 'Morgana O.', 'role' => 'admin', 'avatar' => '/images/avatar-3.svg'],
                'time' => '12 min ago',
                'text' => 'We just use words/200 and call it a day. It is not perfect, but it is consistent.',
                'score' => 9,
            ],
        ],
    ],
    [
        'slug' => 'pcb-power-noise',
        'title' => 'Best practices for power noise on mixed-signal PCBs?',
        'time' => '19:36',
        'published_minutes' => 64,
        'delta' => '+2',
        'tags' => ['hardware', 'pcb'],
        'author' => ['name' => 'Timur K.', 'role' => 'user', 'avatar' => '/images/avatar-2.svg'],
        'body' => "I keep getting noise spikes on mixed-signal boards when sensors boot.\n\nAny layout patterns or grounding rules you follow by default? I am trying to keep routing short, but still see spikes.",
        'answers' => [
            [
                'author' => ['name' => 'Ilya M.', 'role' => 'maker', 'avatar' => '/images/avatar-4.svg'],
                'time' => '32 min ago',
                'text' => 'Split analog and digital ground planes, then connect at one point near the ADC. Also keep the regulator physically away from the sensors.',
                'score' => 11,
            ],
            [
                'author' => ['name' => 'Sveta L.', 'role' => 'maker', 'avatar' => '/images/avatar-5.svg'],
                'time' => '25 min ago',
                'text' => 'Put decouplers as close as possible and avoid long sensor traces running parallel to switching lines.',
                'score' => 7,
            ],
            [
                'author' => ['name' => 'Petya D.', 'role' => 'user', 'avatar' => '/images/avatar-6.svg'],
                'time' => '18 min ago',
                'text' => 'LC filters on the sensor branch helped us a lot. Worth the extra parts.',
                'score' => 4,
            ],
        ],
    ],
    [
        'slug' => 'capstone-scope',
        'title' => 'How do you keep a capstone project scope realistic?',
        'time' => '18:30',
        'published_minutes' => 90,
        'delta' => '+5',
        'tags' => ['planning', 'school'],
        'author' => ['name' => 'Alina S.', 'role' => 'user', 'avatar' => '/images/avatar-7.svg'],
        'body' => "Our capstone team keeps adding features and the scope keeps growing.\n\nAny templates or rules you use to keep it realistic?",
        'answers' => [
            [
                'author' => ['name' => 'Lena S.', 'role' => 'maker', 'avatar' => '/images/avatar-8.svg'],
                'time' => '1 hour ago',
                'text' => 'We freeze scope after week 2, then allow only swaps. New feature must replace something of equal size.',
                'score' => 13,
            ],
            [
                'author' => ['name' => 'Nikita B.', 'role' => 'user', 'avatar' => '/images/avatar-7.svg'],
                'time' => '45 min ago',
                'text' => 'Timebox the MVP to 2 weeks. If it does not fit, cut until it does.',
                'score' => 6,
            ],
        ],
    ],
    [
        'slug' => 'lab-report-format',
        'title' => 'Do you publish lab reports as blog posts or PDFs?',
        'time' => '18:09',
        'published_minutes' => 111,
        'delta' => '+1',
        'tags' => ['writing', 'labs'],
        'author' => ['name' => 'Oleg S.', 'role' => 'user', 'avatar' => '/images/avatar-2.svg'],
        'body' => "We have lab work in PDFs, but nobody reads them.\n\nThinking of converting to a blog-like format. Has anyone done this?",
        'answers' => [
            [
                'author' => ['name' => 'Mila T.', 'role' => 'maker', 'avatar' => '/images/avatar-6.svg'],
                'time' => '52 min ago',
                'text' => 'We publish summaries as posts and keep the full PDF as an attachment. People read summaries, then open the PDF if needed.',
                'score' => 5,
            ],
        ],
    ],
    [
        'slug' => 'team-sync',
        'title' => 'Lightweight ways to keep a student team in sync?',
        'time' => '17:31',
        'published_minutes' => 129,
        'delta' => '+3',
        'tags' => ['team', 'process'],
        'author' => ['name' => 'Katya F.', 'role' => 'user', 'avatar' => '/images/avatar-4.svg'],
        'body' => "We are a 6-person team and keep losing context.\n\nAny lightweight rituals or tools that help without becoming overhead?",
        'answers' => [
            [
                'author' => ['name' => 'Morgana O.', 'role' => 'user', 'avatar' => '/images/avatar-3.svg'],
                'time' => '1 hour ago',
                'text' => 'Weekly 20-min demo with 3 bullets per person. We also keep a shared changelog doc.',
                'score' => 8,
            ],
            [
                'author' => ['name' => 'Timur K.', 'role' => 'user', 'avatar' => '/images/avatar-2.svg'],
                'time' => '40 min ago',
                'text' => 'We use a single “current status” note and update it every Friday. No extra meetings.',
                'score' => 4,
            ],
        ],
    ],
    [
        'slug' => 'prototype-bugs',
        'title' => 'Is it okay to ship a prototype with known bugs?',
        'time' => '16:58',
        'published_minutes' => 142,
        'delta' => '+2',
        'tags' => ['prototype', 'ethics'],
        'author' => ['name' => 'Nastya V.', 'role' => 'user', 'avatar' => '/images/avatar-5.svg'],
        'body' => "We have a demo next week and a few known bugs that do not affect the core flow.\n\nShould we ship or delay?",
        'answers' => [
            [
                'author' => ['name' => 'Ilya M.', 'role' => 'maker', 'avatar' => '/images/avatar-4.svg'],
                'time' => '35 min ago',
                'text' => 'Ship if you can explain the bugs and the core flow is stable. Demo is about learning, not perfection.',
                'score' => 10,
            ],
            [
                'author' => ['name' => 'Dasha N.', 'role' => 'maker', 'avatar' => '/images/avatar-1.svg'],
                'time' => '22 min ago',
                'text' => 'Make a short list of known bugs and keep the demo script away from them.',
                'score' => 6,
            ],
        ],
    ],
    [
        'slug' => 'long-post-structure',
        'title' => 'How do you structure long project posts with diagrams?',
        'time' => '16:22',
        'published_minutes' => 178,
        'delta' => '+1',
        'tags' => ['writing', 'docs'],
        'author' => ['name' => 'Roma I.', 'role' => 'user', 'avatar' => '/images/avatar-6.svg'],
        'body' => "My posts are long and diagram-heavy. Readers drop off.\n\nDo you interleave diagrams with short text blocks or collect them into one section?",
        'answers' => [
            [
                'author' => ['name' => 'Lena S.', 'role' => 'maker', 'avatar' => '/images/avatar-8.svg'],
                'time' => '28 min ago',
                'text' => 'Interleave small diagrams every 2–3 paragraphs. Readers stay in the flow.',
                'score' => 5,
            ],
        ],
    ],
    [
        'slug' => 'experiment-tracking',
        'title' => 'Tools for tracking experiments without slowing down?',
        'time' => '15:50',
        'published_minutes' => 190,
        'delta' => '+1',
        'tags' => ['process', 'notes'],
        'author' => ['name' => 'Misha G.', 'role' => 'user', 'avatar' => '/images/avatar-3.svg'],
        'body' => "We run quick experiments and keep losing notes.\n\nAny lightweight tools that do not slow you down?",
        'answers' => [
            [
                'author' => ['name' => 'Mila T.', 'role' => 'maker', 'avatar' => '/images/avatar-6.svg'],
                'time' => '55 min ago',
                'text' => 'We use a single page “experiment log” in Notion with three fields: date, goal, outcome.',
                'score' => 4,
            ],
        ],
    ],
];

if (!empty($demoAuthors)) {
    $qa_questions = array_map(static function (array $question) use ($nextDemoAuthor) {
        $author = $nextDemoAuthor();
        if ($author) {
            $question['author'] = $author;
        }
        return $question;
    }, $qa_questions);
}

$mapPostToProject = static function (Post $post, array $stats): array {
    return FeedService::mapPostToProjectWithStats($post, $stats);
};

$mapPostToQuestion = static function (Post $post, array $stats): array {
    return FeedService::mapPostToQuestionWithStats($post, $stats);
};

$postSlugExists = static function (string $slug) use ($projects, $qa_questions): bool {
    if (safeHasTable('posts')) {
        return Post::where('slug', $slug)->exists();
    }
    return in_array($slug, collect($projects)->pluck('slug')->merge(collect($qa_questions)->pluck('slug'))->all(), true);
};

$useDbFeed = safeHasTable('posts') && Post::query()->exists();

$feed_tags = [];
$demoTagEntries = [];
if ($useDbFeed) {
    $feed_tags = FeedService::buildFeedTags(15);
}

if (empty($feed_tags)) {
    $tagBuckets = [];
    foreach ($projects as $project) {
        foreach (($project['tags'] ?? []) as $tag) {
            $label = trim((string) $tag);
            if ($label === '') {
                continue;
            }
            $key = Str::slug($label);
            if ($key === '') {
                continue;
            }
            if (!isset($tagBuckets[$key])) {
                $tagBuckets[$key] = ['label' => $label, 'slug' => $key, 'count' => 0];
            }
            $tagBuckets[$key]['count'] += 1;
        }
    }
    foreach ($qa_questions as $question) {
        foreach (($question['tags'] ?? []) as $tag) {
            $label = trim((string) $tag);
            if ($label === '') {
                continue;
            }
            $key = Str::slug($label);
            if ($key === '') {
                continue;
            }
            if (!isset($tagBuckets[$key])) {
                $tagBuckets[$key] = ['label' => $label, 'slug' => $key, 'count' => 0];
            }
            $tagBuckets[$key]['count'] += 1;
        }
    }

    $tagEntries = array_values($tagBuckets);
    usort($tagEntries, static function (array $a, array $b): int {
        $countCompare = ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
        if ($countCompare !== 0) {
            return $countCompare;
        }
        return strcmp($a['label'] ?? '', $b['label'] ?? '');
    });

    $demoTagEntries = $tagEntries;
    $feed_tags = array_values(array_slice($tagEntries, 0, 15));
}

$qa_threads = [];
if ($useDbFeed) {
    $qa_threads = FeedService::buildQaThreads(12);
}

if (empty($qa_threads)) {
    $qa_threads = array_map(static function ($question) {
        $slug = $question['slug'];
        $replies = count($question['answers'] ?? []);
        $score = (int) ($question['score'] ?? 0);
        $deltaValue = '+' . (int) $score;
        if (!str_starts_with($deltaValue, '+')) {
            $deltaValue = '+' . ltrim($deltaValue, '+');
        }
        $minutes = $question['published_minutes'] ?? 0;
        $timeLabel = $question['time'] ?? '';
        if (!$timeLabel && $minutes) {
            $timeLabel = now()->subMinutes($minutes)->diffForHumans();
        }
        return [
            'slug' => $slug,
            'title' => $question['title'],
            'time' => $timeLabel,
            'minutes' => $minutes,
            'replies' => $replies,
            'delta' => $deltaValue,
        ];
    }, $qa_questions);
}

$projectMap = collect($projects)->keyBy('slug');

$feed_page_size = 10;
$feed_projects = [];
$feed_questions = [];
$feed_items_page = [];
$feed_projects_total = 0;
$feed_questions_total = 0;
$feed_projects_offset = 0;
$feed_questions_offset = 0;

$sortFeedItems = static function (array $a, array $b): int {
    return ($a['published_minutes'] ?? 0) <=> ($b['published_minutes'] ?? 0);
};
$sortFeedByScore = static function (array $a, array $b): int {
    $scoreA = (int) ($a['data']['score'] ?? 0);
    $scoreB = (int) ($b['data']['score'] ?? 0);
    if ($scoreA !== $scoreB) {
        return $scoreB <=> $scoreA;
    }
    return ($a['published_minutes'] ?? 0) <=> ($b['published_minutes'] ?? 0);
};
$normalizeFeedFilter = static function (?string $filter): string {
    $value = strtolower((string) $filter);
    return in_array($value, ['best', 'fresh', 'reading'], true) ? $value : 'all';
};
$applyFeedFilter = static function (array $items, string $filter) use ($sortFeedByScore): array {
    if ($filter === 'fresh') {
        return array_values(array_filter($items, static function (array $item): bool {
            return (int) ($item['published_minutes'] ?? 0) <= 180;
        }));
    }
    if ($filter === 'reading') {
        return array_values(array_filter($items, static function (array $item): bool {
            return (int) ($item['data']['read_time_minutes'] ?? 0) >= 8;
        }));
    }
    if ($filter === 'best') {
        $sorted = $items;
        usort($sorted, $sortFeedByScore);
        return array_values($sorted);
    }
    return $items;
};

if ($useDbFeed) {
    $feed_items_page = [];
    $feed_projects_total = 0;
    $feed_questions_total = 0;
    $feed_projects_offset = 0;
    $feed_questions_offset = 0;
} else {
    $qaThreadMap = collect($qa_threads)->keyBy('slug');
    foreach ($projects as $project) {
        $feed_projects[] = [
            'type' => 'project',
            'data' => $project,
            'published_minutes' => (int) ($project['published_minutes'] ?? 0),
        ];
    }
    foreach ($qa_questions as $question) {
        $slug = $question['slug'] ?? '';
        $thread = $slug ? $qaThreadMap->get($slug) : null;
        $replies = $thread['replies'] ?? count($question['answers'] ?? []);
        $score = (int) ($question['score'] ?? 0);
        $question['replies'] = $replies;
        $question['score'] = $score;
        $feed_questions[] = [
            'type' => 'question',
            'data' => $question,
            'published_minutes' => (int) ($question['published_minutes'] ?? 0),
        ];
    }
    usort($feed_projects, $sortFeedItems);
    usort($feed_questions, $sortFeedItems);

    $feed_projects_total = count($feed_projects);
    $feed_questions_total = count($feed_questions);
    $feed_projects_page = array_slice($feed_projects, 0, $feed_page_size);
    $feed_questions_page = array_slice($feed_questions, 0, $feed_page_size);
    $feed_projects_offset = count($feed_projects_page);
    $feed_questions_offset = count($feed_questions_page);
    $feed_items_page = array_merge($feed_projects_page, $feed_questions_page);
    usort($feed_items_page, $sortFeedItems);
}

$top_projects = Cache::remember('feed.top_projects.v1', now()->addMinutes(10), function () use ($projectMap) {
    if (safeHasTable('posts') && safeHasTable('post_upvotes')) {
        $rowsQuery = DB::table('posts')
            ->leftJoin('post_upvotes', 'posts.id', '=', 'post_upvotes.post_id')
            ->where('posts.type', 'post')
            ->groupBy('posts.id', 'posts.slug', 'posts.title')
            ->select('posts.slug', 'posts.title', DB::raw('count(post_upvotes.id) as score'))
            ->orderByDesc('score')
            ->orderBy('posts.title')
            ->limit(4);
        applyVisibilityFilters($rowsQuery, 'posts', null);
        $rows = $rowsQuery->get();

        $entries = [];
        foreach ($rows as $row) {
            $entries[] = [
                'slug' => $row->slug,
                'title' => $row->title,
                'score' => (int) ($row->score ?? 0),
            ];
        }

        if (!empty($entries)) {
            return $entries;
        }
    }

    return $projectMap
        ->values()
        ->map(static fn (array $project) => [
            'slug' => $project['slug'],
            'title' => $project['title'],
            'score' => (int) ($project['score'] ?? 0),
        ])
        ->sortByDesc('score')
        ->take(4)
        ->values()
        ->all();
});

$reading_now = Cache::remember('feed.reading_now.v2', now()->addMinutes(2), function () use ($projectMap) {
    if (!safeHasTable('reading_activity') && !safeHasTable('reading_progress')) {
        return [];
    }

    $windowMinutes = 10;
    if (safeHasTable('reading_activity')) {
        $rows = DB::table('reading_activity')
            ->where('updated_at', '>=', now()->subMinutes($windowMinutes))
            ->groupBy('post_id')
            ->select('post_id', DB::raw('count(distinct ip_hash) as readers'), DB::raw('max(updated_at) as last_read'))
            ->orderByDesc('readers')
            ->orderByDesc('last_read')
            ->limit(3)
            ->get();
    } else {
        $rows = DB::table('reading_progress')
            ->where('updated_at', '>=', now()->subMinutes($windowMinutes))
            ->groupBy('post_id')
            ->select('post_id', DB::raw('count(*) as readers'), DB::raw('max(updated_at) as last_read'))
            ->orderByDesc('readers')
            ->orderByDesc('last_read')
            ->limit(3)
            ->get();
    }

    $postIds = $rows
        ->pluck('post_id')
        ->filter(static fn ($id) => is_numeric($id))
        ->map(static fn ($id) => (int) $id)
        ->unique()
        ->values()
        ->all();

    $postSlugs = $rows
        ->pluck('post_id')
        ->filter(static fn ($id) => is_string($id) && !is_numeric($id))
        ->map(static fn ($id) => (string) $id)
        ->unique()
        ->values()
        ->all();

    $postMapById = collect();
    $postMapBySlug = collect();
    if (safeHasTable('posts')) {
        if (!empty($postIds)) {
            $postQuery = Post::whereIn('id', $postIds);
            applyVisibilityFilters($postQuery, 'posts', null);
            $postMapById = $postQuery->get(['id', 'slug', 'title'])->keyBy('id');
        }
        if (!empty($postSlugs)) {
            $postQuery = Post::whereIn('slug', $postSlugs);
            applyVisibilityFilters($postQuery, 'posts', null);
            $postMapBySlug = $postQuery->get(['id', 'slug', 'title'])->keyBy('slug');
        }
    }

    $entries = [];
    foreach ($rows as $row) {
        $postId = $row->post_id ?? null;
        if ($postId === null) {
            continue;
        }
        $slug = '';
        $title = '';
        if (is_numeric($postId)) {
            $post = $postMapById->get((int) $postId);
            $slug = $post?->slug ?? '';
            $title = $post?->title ?? '';
        } else {
            $post = $postMapBySlug->get((string) $postId);
            if ($post) {
                $slug = $post->slug ?? '';
                $title = $post->title ?? '';
            } else {
                $project = $projectMap->get((string) $postId);
                $slug = $project['slug'] ?? '';
                $title = $project['title'] ?? '';
            }
        }
        if ($slug === '' || $title === '') {
            continue;
        }
        $entries[] = [
            'slug' => $slug,
            'title' => $title,
            'readers' => (int) ($row->readers ?? 0),
            'last_read' => $row->last_read,
        ];
    }

    return $entries;
});

$searchIndex = Cache::remember('search.index.v1', now()->addMinutes(5), function () use ($useDbFeed, $projects, $qa_questions, $demoTagEntries) {
    $items = [];

    if ($useDbFeed) {
        $postsQuery = Post::with('user:id,name,slug')
            ->orderByDesc('created_at');
        applyVisibilityFilters($postsQuery, 'posts', null);
        $posts = $postsQuery
            ->limit(200)
            ->get(['id', 'slug', 'title', 'subtitle', 'type', 'user_id', 'tags']);

        foreach ($posts as $post) {
            $type = $post->type === 'question' ? 'question' : 'post';
            $items[] = [
                'type' => $type,
                'title' => $post->title,
                'subtitle' => $post->subtitle,
                'url' => $type === 'question'
                    ? url('/questions/' . $post->slug)
                    : url('/projects/' . $post->slug),
                'slug' => $post->slug,
                'author' => $post->user?->name ?? null,
                'keywords' => is_array($post->tags) ? implode(' ', $post->tags) : null,
            ];
        }
    } else {
        foreach ($projects as $project) {
            $items[] = [
                'type' => 'post',
                'title' => $project['title'] ?? '',
                'subtitle' => $project['subtitle'] ?? $project['context'] ?? null,
                'url' => url('/projects/' . $project['slug']),
                'slug' => $project['slug'],
                'author' => $project['author']['name'] ?? null,
                'keywords' => !empty($project['tags']) ? implode(' ', $project['tags']) : null,
            ];
        }
        foreach ($qa_questions as $question) {
            $items[] = [
                'type' => 'question',
                'title' => $question['title'] ?? '',
                'subtitle' => $question['body'] ?? null,
                'url' => url('/questions/' . $question['slug']),
                'slug' => $question['slug'],
                'author' => $question['author']['name'] ?? null,
                'keywords' => !empty($question['tags']) ? implode(' ', $question['tags']) : null,
            ];
        }
    }

    $tagEntries = $demoTagEntries;
    if (empty($tagEntries) && $useDbFeed) {
        $tagEntries = FeedService::buildFeedTags(200, 500);
    }
    $tagEntries = array_slice($tagEntries, 0, 200);
    foreach ($tagEntries as $entry) {
        $label = trim((string) ($entry['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $slug = $entry['slug'] ?? Str::slug($label);
        if ($slug === '') {
            continue;
        }
        $count = (int) ($entry['count'] ?? 0);
        $items[] = [
            'type' => 'tag',
            'title' => '#' . $label,
            'subtitle' => __('ui.search_tag_posts', ['count' => $count]),
            'url' => url('/?tags=' . $slug),
            'slug' => $slug,
            'author' => null,
            'keywords' => implode(' ', array_filter([$label, '#' . $label, $slug])),
        ];
    }

    if (safeHasTable('users') && User::query()->exists()) {
        $users = User::query()->orderBy('name')->limit(200)->get(['id', 'name', 'slug', 'role', 'bio']);
        foreach ($users as $user) {
            $slug = $user->slug ?? Str::slug($user->name ?? '');
            if ($slug === '') {
                continue;
            }
            $items[] = [
                'type' => 'user',
                'title' => $user->name ?? '',
                'subtitle' => $user->role ?? null,
                'url' => url('/profile/' . $slug),
                'slug' => $slug,
                'author' => null,
                'keywords' => $user->bio ?? null,
            ];
        }
    } else {
        $demoUsers = [];
        foreach ([$projects, $qa_questions] as $collection) {
            foreach ($collection as $entry) {
                $author = $entry['author']['name'] ?? null;
                if (!$author) {
                    continue;
                }
                $slug = $entry['author']['slug'] ?? Str::slug($author);
                if ($slug === '') {
                    continue;
                }
                $role = $entry['author']['role'] ?? null;
                $demoUsers[$slug] = [
                    'type' => 'user',
                    'title' => $author,
                    'subtitle' => $role,
                    'url' => url('/profile/' . $slug),
                    'slug' => $slug,
                    'author' => null,
                    'keywords' => null,
                ];
            }
        }
        $items = array_merge($items, array_values($demoUsers));
    }

    return $items;
});

$topbarPromo = pickTopbarPromo();

view()->composer(['layouts.app', 'layouts.support'], function ($view) {
    $payload = app(\App\Services\NotificationService::class)
        ->buildPayload((array) config('notifications.seed', []));
    $view->with([
        'unreadNotifications' => $payload['unreadPreview'],
        'unreadCount' => $payload['unreadCount'],
    ]);
});

view()->share([
    'searchIndex' => $searchIndex,
    'topbar_promo' => $topbarPromo,
]);

Route::get('/promos/{promo}/click', function (TopbarPromo $promo) {
    if (safeHasColumn('topbar_promos', 'clicks_count')) {
        TopbarPromo::query()
            ->where('id', $promo->id)
            ->update(['clicks_count' => DB::raw('clicks_count + 1')]);
    }
    return redirect()->away($promo->url);
})->name('promos.click');

Route::get('/feed/chunk', function (Request $request) use ($feed_projects, $feed_questions, $feed_page_size, $useDbFeed, $normalizeFeedFilter, $applyFeedFilter) {
    $stream = $request->query('stream', 'projects');
    $stream = in_array($stream, ['projects', 'questions'], true) ? $stream : 'projects';
    $offset = max(0, (int) $request->query('offset', 0));
    $limit = (int) $request->query('limit', $feed_page_size);
    $limit = max(1, min(20, $limit));
    $filter = $normalizeFeedFilter($request->query('filter'));

    if ($useDbFeed) {
        $result = FeedService::buildFeedChunk($stream, $offset, $limit, Auth::user(), $filter);
        $items = array_map(static function (array $item) {
            return view('partials.feed-item', ['item' => $item])->render();
        }, $result['items']);

        return response()->json([
            'items' => $items,
            'next_offset' => $result['next_offset'],
            'has_more' => $result['has_more'],
            'total' => $result['total'],
            'stream' => $result['stream'],
        ]);
    }

    $source = $stream === 'questions' ? $feed_questions : $feed_projects;
    if ($filter !== 'all') {
        $source = $applyFeedFilter($source, $filter);
    }
    $slice = array_slice($source, $offset, $limit);
    $items = array_map(static function (array $item) {
        return view('partials.feed-item', ['item' => $item])->render();
    }, $slice);

    $nextOffset = $offset + count($slice);
    $total = count($source);

    return response()->json([
        'items' => $items,
        'next_offset' => $nextOffset,
        'has_more' => $nextOffset < $total,
        'total' => $total,
        'stream' => $stream,
    ]);
})->name('feed.chunk');

Route::get('/', function (Request $request) use ($feed_items_page, $feed_tags, $qa_threads, $top_projects, $reading_now, $feed_projects_total, $feed_questions_total, $feed_projects_offset, $feed_questions_offset, $feed_page_size, $useDbFeed, $feed_projects, $feed_questions, $normalizeFeedFilter, $applyFeedFilter, $sortFeedItems, $sortFeedByScore) {
    $current_user = app(\App\Services\UserPayloadService::class)->currentUserPayload();
    $feedItems = $feed_items_page;
    $projectsTotal = $feed_projects_total;
    $questionsTotal = $feed_questions_total;
    $projectsOffset = $feed_projects_offset;
    $questionsOffset = $feed_questions_offset;
    $filter = $normalizeFeedFilter($request->query('filter'));
    if ($useDbFeed) {
        $feedData = FeedService::buildInitialFeed(Auth::user(), $feed_page_size, $filter);
        $feedItems = $feedData['items'];
        $projectsTotal = $feedData['projects_total'];
        $questionsTotal = $feedData['questions_total'];
        $projectsOffset = $feedData['projects_offset'];
        $questionsOffset = $feedData['questions_offset'];
    } else {
        $filteredProjects = $applyFeedFilter($feed_projects, $filter);
        $filteredQuestions = $applyFeedFilter($feed_questions, $filter);
        $projectsTotal = count($filteredProjects);
        $questionsTotal = count($filteredQuestions);
        $projectsPage = array_slice($filteredProjects, 0, $feed_page_size);
        $questionsPage = array_slice($filteredQuestions, 0, $feed_page_size);
        $projectsOffset = count($projectsPage);
        $questionsOffset = count($questionsPage);
        $feedItems = array_merge($projectsPage, $questionsPage);
        $sorter = $filter === 'best' ? $sortFeedByScore : $sortFeedItems;
        usort($feedItems, $sorter);
    }

    $subscriptions = [];
    $viewer = Auth::user();
    if ($viewer && safeHasTable('user_follows') && safeHasTable('users')) {
        $followRows = DB::table('user_follows')
            ->join('users', 'user_follows.following_id', '=', 'users.id')
            ->where('user_follows.follower_id', $viewer->id)
            ->orderBy('users.name')
            ->limit(6)
            ->get(['users.id', 'users.name', 'users.slug']);
        foreach ($followRows as $row) {
            $slug = $row->slug ?? Str::slug((string) ($row->name ?? ''));
            if ($slug === '') {
                $slug = 'user-' . $row->id;
            }
            $count = safeHasTable('posts')
                ? DB::table('posts')->where('user_id', $row->id)->count()
                : 0;
            $subscriptions[] = [
                'name' => $row->name,
                'slug' => $slug,
                'count' => (int) $count,
            ];
        }
    }

    return view('feed', [
        'feed_items' => $feedItems,
        'feed_tags' => $feed_tags,
        'current_user' => $current_user,
        'qa_threads' => $qa_threads,
        'top_projects' => $top_projects,
        'reading_now' => $reading_now,
        'subscriptions' => $subscriptions,
        'feed_projects_total' => $projectsTotal,
        'feed_questions_total' => $questionsTotal,
        'feed_projects_offset' => $projectsOffset,
        'feed_questions_offset' => $questionsOffset,
        'feed_page_size' => $feed_page_size,
    ]);
})->name('feed');

Route::get('/projects/{slug}', function (string $slug) use ($projects, $mapPostToProject, $preparePostStats) {
    $viewer = Auth::user();
    $project = collect($projects)->firstWhere('slug', $slug);
    if (safeHasTable('posts')) {
        $dbPost = Post::with(['user', 'editedBy'])->where('slug', $slug)->where('type', 'post')->first();
        if ($dbPost) {
            if (safeHasColumn('users', 'is_banned') && $dbPost->user?->is_banned) {
                abort(404);
            }
            if (!canViewHiddenContent($viewer, $dbPost->user_id)) {
                $isHidden = (bool) ($dbPost->is_hidden ?? false);
                $status = (string) ($dbPost->moderation_status ?? 'approved');
                if ($isHidden || $status !== 'approved') {
                    abort(404);
                }
            }
            $stats = $preparePostStats([$dbPost]);
            $project = $mapPostToProject($dbPost, $stats);
        }
    }

    abort_unless($project, 404);
    $projectMarkdown = (string) ($project['body_markdown'] ?? '');
    if (trim($projectMarkdown) !== '') {
        $project['body_html'] = app(\App\Services\MarkdownService::class)->render($projectMarkdown);
    }
    $commentPageSize = 15;
    $commentRows = [];
    $commentTotal = 0;
    $reviewRows = [];
    if (safeHasTable('post_comments')) {
        $commentCountQuery = PostComment::where('post_slug', $slug)->whereNull('parent_id');
        applyVisibilityFilters($commentCountQuery, 'post_comments', $viewer);
        $commentTotal = $commentCountQuery->count();
        if ($commentTotal > 0) {
            $parentRowsQuery = PostComment::with('user')
                ->where('post_slug', $slug)
                ->whereNull('parent_id');
            applyVisibilityFilters($parentRowsQuery, 'post_comments', $viewer);
            $parentRows = $parentRowsQuery
                ->latest()
                ->limit($commentPageSize)
                ->get();

            $parentIds = $parentRows->pluck('id')->values();
            $replyRows = $parentIds->isEmpty()
                ? collect()
                : tap(PostComment::with('user')
                    ->where('post_slug', $slug)
                    ->whereIn('parent_id', $parentIds), function ($query) use ($viewer) {
                    applyVisibilityFilters($query, 'post_comments', $viewer);
                })
                    ->orderBy('created_at')
                    ->get();

            $replyMap = $replyRows
                ->map(function (PostComment $reply) {
                    $author = $reply->user;
                    return [
                        'id' => $reply->id,
                        'author' => [
                            'name' => $author?->name ?? 'Anonymous',
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        ],
                        'time' => $reply->created_at?->diffForHumans() ?? 'just now',
                        'text' => $reply->body,
                        'useful' => $reply->useful ?? 0,
                        'created_at' => $reply->created_at ? $reply->created_at->getTimestamp() * 1000 : null,
                        'parent_id' => $reply->parent_id,
                        'is_hidden' => (bool) ($reply->is_hidden ?? false),
                        'moderation_status' => (string) ($reply->moderation_status ?? 'approved'),
                    ];
                })
                ->groupBy('parent_id')
                ->map(fn ($group) => $group->values()->all());

            $commentRows = $parentRows
                ->map(function (PostComment $comment) use ($replyMap) {
                    $author = $comment->user;
                    return [
                        'id' => $comment->id,
                        'author' => $author?->name ?? 'Anonymous',
                        'author_slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                        'time' => $comment->created_at?->diffForHumans() ?? 'just now',
                        'section' => $comment->section ?? 'General',
                        'text' => $comment->body,
                        'useful' => $comment->useful ?? 0,
                        'role' => $author?->role ?? 'user',
                        'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                        'replies' => $replyMap[$comment->id] ?? [],
                        'is_hidden' => (bool) ($comment->is_hidden ?? false),
                        'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                    ];
                })
                ->toArray();
        }
    }

    if ($commentTotal === 0) {
        $demoComments = $project['comments'] ?? [];
        $commentTotal = count($demoComments);
        $commentRows = array_slice($demoComments, 0, $commentPageSize);
    }

    if (safeHasTable('post_reviews')) {
        $reviewQuery = PostReview::with('user')
            ->where('post_slug', $slug);
        applyVisibilityFilters($reviewQuery, 'post_reviews', $viewer);
        $reviewRows = $reviewQuery
            ->latest()
            ->get()
              ->map(function (PostReview $review) {
                  $author = $review->user;
                  return [
                      'id' => $review->id,
                      'author' => [
                          'id' => $author?->id,
                          'name' => $author?->name ?? 'Anonymous',
                          'role' => $author?->role ?? 'user',
                          'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                          'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                          'note' => $author?->role === 'maker' ? 'Maker review' : null,
                      ],
                    'time' => $review->created_at?->diffForHumans() ?? 'just now',
                    'improve' => $review->improve,
                    'why' => $review->why,
                    'how' => $review->how,
                    'useful' => 0,
                    'created_at' => $review->created_at ? $review->created_at->getTimestamp() * 1000 : null,
                    'is_hidden' => (bool) ($review->is_hidden ?? false),
                    'moderation_status' => (string) ($review->moderation_status ?? 'approved'),
                ];
            })
            ->toArray();
    }

    $project['comments'] = array_values($commentRows);
    $project['comments_total'] = $commentTotal;
    $project['comments_offset'] = count($commentRows);
    $project['reviews'] = array_values(array_merge($project['reviews'] ?? [], $reviewRows));
    $current_user = app(\App\Services\UserPayloadService::class)->currentUserPayload();
    return view('project', ['project' => $project, 'current_user' => $current_user]);
})->name('project');

Route::post('/projects/{slug}/comments', function (StoreCommentRequest $request, string $slug) use ($postSlugExists) {
    if (!safeHasTable('post_comments')) {
        return response()->json(['message' => 'Comments table missing'], 503);
    }
    if (!$postSlugExists($slug)) {
        return response()->json(['message' => 'Post not found'], 404);
    }
    $data = $request->validated();

    $user = $request->user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    if (safeHasTable('posts')) {
        $post = Post::where('slug', $slug)->where('type', 'post')->first();
        if ($post && !canViewHiddenContent($user, $post->user_id)) {
            $isHidden = (bool) ($post->is_hidden ?? false);
            $status = (string) ($post->moderation_status ?? 'approved');
            if ($isHidden || $status !== 'approved') {
                return response()->json(['message' => 'Post not found'], 404);
            }
        }
    }

    $parentId = $data['parent_id'] ?? null;
    if ($parentId) {
        $parent = PostComment::where('id', $parentId)->where('post_slug', $slug)->first();
        if (!$parent) {
            return response()->json(['message' => 'Invalid parent comment'], 422);
        }
    }

    $body = (string) ($data['body'] ?? '');
    $section = $data['section'] ?? null;
    $textModerationResult = [
        'flagged' => false,
        'summary' => '',
        'details' => [],
    ];
    if (!$user->hasRole('moderator')) {
        $textModerationResult = app(TextModerationService::class)->analyze($body, [
            'type' => 'comment',
        ]);
    }
    $textModerationFlagged = (bool) ($textModerationResult['flagged'] ?? false);

    $commentPayload = [
        'post_slug' => $slug,
        'user_id' => $user->id,
        'body' => $body,
        'section' => $section,
        'useful' => 0,
        'parent_id' => $parentId ?: null,
    ];

    if ($textModerationFlagged) {
        if (safeHasColumn('post_comments', 'moderation_status')) {
            $commentPayload['moderation_status'] = 'pending';
        }
        if (safeHasColumn('post_comments', 'is_hidden')) {
            $commentPayload['is_hidden'] = true;
        }
        if (safeHasColumn('post_comments', 'hidden_at')) {
            $commentPayload['hidden_at'] = now();
        }
        if (safeHasColumn('post_comments', 'hidden_by')) {
            $commentPayload['hidden_by'] = null;
        }
    }

    $comment = PostComment::create($commentPayload);

    if ($textModerationFlagged && safeHasTable('content_reports')) {
        $summary = trim((string) ($textModerationResult['summary'] ?? ''));
        $detailText = $summary !== '' ? $summary : 'Text moderation flagged comment.';
        ContentReport::create([
            'user_id' => $user->id,
            'content_type' => 'comment',
            'content_id' => (string) $comment->id,
            'content_url' => resolvePostUrl($slug) . '#comment-' . $comment->id,
            'reason' => 'admin_flag',
            'details' => $detailText,
        ]);
    }

    return response()->json([
        'id' => $comment->id,
        'author' => $user->name,
        'author_slug' => $user->slug ?? Str::slug($user->name ?? ''),
        'role' => $user->roleKey(),
        'role_label' => __('ui.roles.' . ($user->role ?? 'user')),
        'time' => __('ui.project.comment_just_now'),
        'text' => $comment->body,
        'section' => $comment->section,
        'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
        'parent_id' => $comment->parent_id,
        'is_hidden' => (bool) ($comment->is_hidden ?? false),
        'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
    ]);
})->middleware(['auth', 'verified', 'account.age', 'throttle:comments'])->name('project.comments.store');

Route::get('/projects/{slug}/comments/chunk', function (Request $request, string $slug) use ($projects) {
    $viewer = $request->user();
    $limit = max(1, min(30, (int) $request->query('limit', 15)));
    $offset = max(0, (int) $request->query('offset', 0));
    $commentRows = [];
    $commentTotal = 0;
    $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);

    $project = collect($projects)->firstWhere('slug', $slug);
    $dbExists = safeHasTable('posts')
        && Post::query()->where('slug', $slug)->where('type', 'post')->exists();
    $postAuthorSlug = '';
    if ($project && isset($project['author'])) {
        $postAuthorSlug = $project['author']['slug'] ?? Str::slug($project['author']['name'] ?? '');
    }
    if ($dbExists) {
        $post = Post::with(['user', 'editedBy'])->where('slug', $slug)->where('type', 'post')->first();
        if ($post && !canViewHiddenContent($viewer, $post->user_id)) {
            $isHidden = (bool) ($post->is_hidden ?? false);
            $status = (string) ($post->moderation_status ?? 'approved');
            if ($isHidden || $status !== 'approved') {
                return response()->json(['items' => [], 'total' => 0, 'next_offset' => 0, 'has_more' => false]);
            }
        }
        $postAuthorSlug = $post?->user?->slug ?? Str::slug($post?->user?->name ?? '') ?? $postAuthorSlug;
    }

    if (!$project && !$dbExists) {
        return response()->json(['items' => [], 'total' => 0, 'next_offset' => 0, 'has_more' => false]);
    }

    if (safeHasTable('post_comments')) {
        $commentCountQuery = PostComment::where('post_slug', $slug)->whereNull('parent_id');
        applyVisibilityFilters($commentCountQuery, 'post_comments', $viewer);
        $commentTotal = $commentCountQuery->count();
        if ($commentTotal > 0) {
            $parentRowsQuery = PostComment::with('user')
                ->where('post_slug', $slug)
                ->whereNull('parent_id');
            applyVisibilityFilters($parentRowsQuery, 'post_comments', $viewer);
            $parentRows = $parentRowsQuery
                ->latest()
                ->skip($offset)
                ->take($limit)
                ->get();

            $parentIds = $parentRows->pluck('id')->values();
            $replyRows = $parentIds->isEmpty()
                ? collect()
                : tap(PostComment::with('user')
                    ->where('post_slug', $slug)
                    ->whereIn('parent_id', $parentIds), function ($query) use ($viewer) {
                    applyVisibilityFilters($query, 'post_comments', $viewer);
                })
                    ->orderBy('created_at')
                    ->get();

            $replyMap = $replyRows
                ->map(function (PostComment $reply) {
                    $author = $reply->user;
                    return [
                        'id' => $reply->id,
                        'author' => [
                            'name' => $author?->name ?? 'Anonymous',
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        ],
                        'time' => $reply->created_at?->diffForHumans() ?? 'just now',
                        'text' => $reply->body,
                        'useful' => $reply->useful ?? 0,
                        'created_at' => $reply->created_at ? $reply->created_at->getTimestamp() * 1000 : null,
                        'parent_id' => $reply->parent_id,
                        'is_hidden' => (bool) ($reply->is_hidden ?? false),
                        'moderation_status' => (string) ($reply->moderation_status ?? 'approved'),
                    ];
                })
                ->groupBy('parent_id')
                ->map(fn ($group) => $group->values()->all());

            $commentRows = $parentRows
                ->map(function (PostComment $comment) use ($replyMap) {
                    $author = $comment->user;
                    return [
                        'id' => $comment->id,
                        'author' => $author?->name ?? 'Anonymous',
                        'author_slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                        'time' => $comment->created_at?->diffForHumans() ?? 'just now',
                        'section' => $comment->section ?? 'General',
                        'text' => $comment->body,
                        'useful' => $comment->useful ?? 0,
                        'role' => $author?->role ?? 'user',
                        'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                        'replies' => $replyMap[$comment->id] ?? [],
                        'is_hidden' => (bool) ($comment->is_hidden ?? false),
                        'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                    ];
                })
                ->toArray();
        }
    }

    if ($commentTotal === 0) {
        $demoComments = $project['comments'] ?? [];
        $commentTotal = count($demoComments);
        $commentRows = array_slice($demoComments, $offset, $limit);
    }

    $items = [];
    foreach (array_values($commentRows) as $index => $comment) {
        $items[] = view('partials.project-comment', [
            'comment' => $comment,
            'commentIndex' => $offset + $index,
            'roleKeys' => $roleKeys,
            'postAuthorSlug' => $postAuthorSlug,
        ])->render();
    }

    $nextOffset = $offset + count($commentRows);

    return response()->json([
        'items' => $items,
        'total' => $commentTotal,
        'next_offset' => $nextOffset,
        'has_more' => $nextOffset < $commentTotal,
    ]);
})->name('project.comments.chunk');

Route::post('/projects/{slug}/reviews', function (StoreReviewRequest $request, string $slug) use ($postSlugExists) {
    if (!$postSlugExists($slug)) {
        return response()->json(['message' => 'Post not found'], 404);
    }
    $data = $request->validated();

    $user = $request->user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    if (!$user->hasRole('maker')) {
        return response()->json(['message' => 'Forbidden'], 403);
    }
    if (!safeHasTable('post_reviews')) {
        return response()->json(['message' => 'Reviews table missing'], 503);
    }

    if (safeHasTable('posts')) {
        $post = Post::where('slug', $slug)->where('type', 'post')->first();
        if ($post && !canViewHiddenContent($user, $post->user_id)) {
            $isHidden = (bool) ($post->is_hidden ?? false);
            $status = (string) ($post->moderation_status ?? 'approved');
            if ($isHidden || $status !== 'approved') {
                return response()->json(['message' => 'Post not found'], 404);
            }
        }
    }

    $improve = (string) ($data['improve'] ?? '');
    $why = (string) ($data['why'] ?? '');
    $how = (string) ($data['how'] ?? '');
    $reviewText = trim(implode("\n\n", array_filter([$improve, $why, $how], static fn ($value) => trim((string) $value) !== '')));
    $textModerationResult = [
        'flagged' => false,
        'summary' => '',
        'details' => [],
    ];
    if (!$user->hasRole('moderator')) {
        $textModerationResult = app(TextModerationService::class)->analyze($reviewText, [
            'type' => 'review',
        ]);
    }
    $textModerationFlagged = (bool) ($textModerationResult['flagged'] ?? false);

    $reviewPayload = [
        'post_slug' => $slug,
        'user_id' => $user->id,
        'improve' => $improve,
        'why' => $why,
        'how' => $how,
    ];

    if ($textModerationFlagged) {
        if (safeHasColumn('post_reviews', 'moderation_status')) {
            $reviewPayload['moderation_status'] = 'pending';
        }
        if (safeHasColumn('post_reviews', 'is_hidden')) {
            $reviewPayload['is_hidden'] = true;
        }
        if (safeHasColumn('post_reviews', 'hidden_at')) {
            $reviewPayload['hidden_at'] = now();
        }
        if (safeHasColumn('post_reviews', 'hidden_by')) {
            $reviewPayload['hidden_by'] = null;
        }
    }

    $review = PostReview::create($reviewPayload);

    if ($textModerationFlagged && safeHasTable('content_reports')) {
        $summary = trim((string) ($textModerationResult['summary'] ?? ''));
        $detailText = $summary !== '' ? $summary : 'Text moderation flagged review.';
        ContentReport::create([
            'user_id' => $user->id,
            'content_type' => 'review',
            'content_id' => (string) $review->id,
            'content_url' => resolvePostUrl($slug) . '#review-' . $review->id,
            'reason' => 'admin_flag',
            'details' => $detailText,
        ]);
    }

    return response()->json([
        'id' => $review->id,
        'author' => $user->name,
        'role' => $user->role ?? 'user',
        'role_label' => __('ui.roles.' . ($user->role ?? 'user')),
        'time' => __('ui.project.comment_just_now'),
        'improve' => $review->improve,
        'why' => $review->why,
        'how' => $review->how,
        'created_at' => $review->created_at ? $review->created_at->getTimestamp() * 1000 : null,
        'is_hidden' => (bool) ($review->is_hidden ?? false),
        'moderation_status' => (string) ($review->moderation_status ?? 'approved'),
    ]);
})->middleware(['auth', 'verified', 'account.age', 'throttle:reviews'])->name('project.reviews.store');

Route::get('/questions/{slug}', function (string $slug) use ($qa_questions, $mapPostToQuestion, $preparePostStats) {
    $viewer = Auth::user();
    $question = collect($qa_questions)->firstWhere('slug', $slug);
    if (safeHasTable('posts')) {
        $dbPost = Post::with(['user', 'editedBy'])->where('slug', $slug)->where('type', 'question')->first();
        if ($dbPost) {
            if (safeHasColumn('users', 'is_banned') && $dbPost->user?->is_banned) {
                abort(404);
            }
            if (!canViewHiddenContent($viewer, $dbPost->user_id)) {
                $isHidden = (bool) ($dbPost->is_hidden ?? false);
                $status = (string) ($dbPost->moderation_status ?? 'approved');
                if ($isHidden || $status !== 'approved') {
                    abort(404);
                }
            }
            $stats = $preparePostStats([$dbPost]);
            $question = $mapPostToQuestion($dbPost, $stats);
        }
    }
    abort_unless($question, 404);
    $questionMarkdown = (string) ($question['body_markdown'] ?? $question['body'] ?? '');
    if (trim($questionMarkdown) !== '') {
        $question['body_html'] = app(\App\Services\MarkdownService::class)->render($questionMarkdown);
    }

    $commentPageSize = 15;
    $answers = $question['answers'] ?? [];
    $answerTotal = 0;

    if (safeHasTable('post_comments')) {
        $answerCountQuery = PostComment::where('post_slug', $slug)->whereNull('parent_id');
        applyVisibilityFilters($answerCountQuery, 'post_comments', $viewer);
        $answerTotal = $answerCountQuery->count();
        if ($answerTotal > 0) {
            $parentRowsQuery = PostComment::with('user')
                ->where('post_slug', $slug)
                ->whereNull('parent_id');
            applyVisibilityFilters($parentRowsQuery, 'post_comments', $viewer);
            $parentRows = $parentRowsQuery
                ->latest()
                ->limit($commentPageSize)
                ->get();
            $parentIds = $parentRows->pluck('id')->values();
            $replyRows = $parentIds->isEmpty()
                ? collect()
                : tap(PostComment::with('user')
                    ->where('post_slug', $slug)
                    ->whereIn('parent_id', $parentIds), function ($query) use ($viewer) {
                    applyVisibilityFilters($query, 'post_comments', $viewer);
                })
                    ->orderBy('created_at')
                    ->get();

            $replyMap = $replyRows
                ->map(function (PostComment $comment) {
                    $author = $comment->user;
                    return [
                        'author' => [
                            'name' => $author?->name ?? __('ui.project.anonymous'),
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        ],
                        'time' => $comment->created_at?->diffForHumans() ?? '',
                        'text' => $comment->body,
                        'id' => $comment->id,
                        'parent_id' => $comment->parent_id,
                        'useful' => $comment->useful ?? 0,
                        'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                        'is_hidden' => (bool) ($comment->is_hidden ?? false),
                        'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                    ];
                })
                ->groupBy('parent_id')
                ->map(fn ($group) => $group->values()->all());

            $answers = $parentRows
                ->map(function (PostComment $comment) use ($replyMap) {
                    $author = $comment->user;
                    $entry = [
                        'author' => [
                            'name' => $author?->name ?? __('ui.project.anonymous'),
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        ],
                        'time' => $comment->created_at?->diffForHumans() ?? '',
                        'text' => $comment->body,
                        'id' => $comment->id,
                        'parent_id' => $comment->parent_id,
                        'useful' => $comment->useful ?? 0,
                        'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                        'is_hidden' => (bool) ($comment->is_hidden ?? false),
                        'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                    ];
                    $replies = $replyMap->get($comment->id, []);
                    if (!empty($replies)) {
                        $entry['replies'] = $replies;
                    }
                    return $entry;
                })
                ->values()
                ->all();
        }
    }

    if ($answerTotal === 0) {
        $demoAnswers = $question['answers'] ?? [];
        $answerTotal = count($demoAnswers);
        $answers = array_slice($demoAnswers, 0, $commentPageSize);
    }

    $question['answers'] = $answers;
    $question['answers_total'] = $answerTotal;
    $question['answers_offset'] = count($answers);

    $current_user = app(\App\Services\UserPayloadService::class)->currentUserPayload();
    return view('questions.show', ['question' => $question, 'current_user' => $current_user]);
})->name('questions.show');

Route::get('/questions/{slug}/comments/chunk', function (Request $request, string $slug) use ($qa_questions) {
    $viewer = $request->user();
    $limit = max(1, min(30, (int) $request->query('limit', 15)));
    $offset = max(0, (int) $request->query('offset', 0));
    $answers = [];
    $total = 0;
    $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);

    $question = collect($qa_questions)->firstWhere('slug', $slug);
    $dbExists = safeHasTable('posts')
        && Post::query()->where('slug', $slug)->where('type', 'question')->exists();

    if (!$question && !$dbExists) {
        return response()->json(['items' => [], 'total' => 0, 'next_offset' => 0, 'has_more' => false]);
    }

    if (safeHasTable('posts')) {
        $post = Post::where('slug', $slug)->where('type', 'question')->first();
        if ($post && !canViewHiddenContent($viewer, $post->user_id)) {
            $isHidden = (bool) ($post->is_hidden ?? false);
            $status = (string) ($post->moderation_status ?? 'approved');
            if ($isHidden || $status !== 'approved') {
                return response()->json(['items' => [], 'total' => 0, 'next_offset' => 0, 'has_more' => false]);
            }
        }
    }

    if (safeHasTable('post_comments')) {
        $answerCountQuery = PostComment::where('post_slug', $slug)->whereNull('parent_id');
        applyVisibilityFilters($answerCountQuery, 'post_comments', $viewer);
        $total = $answerCountQuery->count();
        if ($total > 0) {
            $parentRowsQuery = PostComment::with('user')
                ->where('post_slug', $slug)
                ->whereNull('parent_id');
            applyVisibilityFilters($parentRowsQuery, 'post_comments', $viewer);
            $parentRows = $parentRowsQuery
                ->latest()
                ->skip($offset)
                ->take($limit)
                ->get();
            $parentIds = $parentRows->pluck('id')->values();
            $replyRows = $parentIds->isEmpty()
                ? collect()
                : tap(PostComment::with('user')
                    ->where('post_slug', $slug)
                    ->whereIn('parent_id', $parentIds), function ($query) use ($viewer) {
                    applyVisibilityFilters($query, 'post_comments', $viewer);
                })
                    ->orderBy('created_at')
                    ->get();
            $replyMap = $replyRows
                ->map(function (PostComment $comment) {
                    $author = $comment->user;
                    return [
                        'author' => [
                            'name' => $author?->name ?? __('ui.project.anonymous'),
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        ],
                        'time' => $comment->created_at?->diffForHumans() ?? '',
                        'text' => $comment->body,
                        'id' => $comment->id,
                        'parent_id' => $comment->parent_id,
                        'useful' => $comment->useful ?? 0,
                        'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                        'is_hidden' => (bool) ($comment->is_hidden ?? false),
                        'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                    ];
                })
                ->groupBy('parent_id')
                ->map(fn ($group) => $group->values()->all());
            $answers = $parentRows
                ->map(function (PostComment $comment) use ($replyMap) {
                    $author = $comment->user;
                    $entry = [
                        'author' => [
                            'name' => $author?->name ?? __('ui.project.anonymous'),
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        ],
                        'time' => $comment->created_at?->diffForHumans() ?? '',
                        'text' => $comment->body,
                        'id' => $comment->id,
                        'parent_id' => $comment->parent_id,
                        'useful' => $comment->useful ?? 0,
                        'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                        'is_hidden' => (bool) ($comment->is_hidden ?? false),
                        'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                    ];
                    $replies = $replyMap->get($comment->id, []);
                    if (!empty($replies)) {
                        $entry['replies'] = $replies;
                    }
                    return $entry;
                })
                ->values()
                ->all();
        }
    }

    if ($total === 0) {
        $demoAnswers = $question['answers'] ?? [];
        $total = count($demoAnswers);
        $answers = array_slice($demoAnswers, $offset, $limit);
    }

    $items = [];
    foreach (array_values($answers) as $index => $answer) {
        $items[] = view('partials.qa-answer', [
            'answer' => $answer,
            'answerIndex' => $offset + $index,
            'questionSlug' => $slug,
            'roleKeys' => $roleKeys,
        ])->render();
    }

    $nextOffset = $offset + count($answers);

    return response()->json([
        'items' => $items,
        'total' => $total,
        'next_offset' => $nextOffset,
        'has_more' => $nextOffset < $total,
    ]);
})->name('questions.comments.chunk');

Route::post('/reading-progress', function (Request $request) use ($postSlugExists) {
    $data = $request->validate([
        'post_id' => ['required', 'string', 'max:190'],
        'percent' => ['required', 'integer', 'min:0', 'max:100'],
        'anchor' => ['nullable', 'string', 'max:190'],
    ]);

    if (!$postSlugExists($data['post_id'])) {
        return response()->json(['ok' => true]);
    }

    $timestamp = now();
    if (safeHasTable('reading_activity')) {
        $ip = (string) ($request->ip() ?? '');
        if ($ip !== '') {
            $salt = (string) config('app.key');
            $ipHash = hash('sha256', $salt . '|' . $ip);
            DB::table('reading_activity')->upsert(
                [
                    [
                        'ip_hash' => $ipHash,
                        'post_id' => $data['post_id'],
                        'updated_at' => $timestamp,
                        'created_at' => $timestamp,
                    ],
                ],
                ['ip_hash', 'post_id'],
                ['updated_at'],
            );
        }
    }

    if (!safeHasTable('reading_progress')) {
        return response()->json(['ok' => true]);
    }

    $userId = Auth::id();
    if (!$userId || !DB::table('users')->where('id', $userId)->exists()) {
        return response()->json(['ok' => true]);
    }

    DB::table('reading_progress')->upsert(
        [
            [
                'user_id' => $userId,
                'post_id' => $data['post_id'],
                'percent' => $data['percent'],
                'anchor' => $data['anchor'],
                'updated_at' => $timestamp,
                'created_at' => $timestamp,
            ],
        ],
        ['user_id', 'post_id'],
        ['percent', 'anchor', 'updated_at'],
    );

    return response()->json(['ok' => true]);
})->middleware('throttle:reading-progress')->name('reading-progress');

Route::post('/uploads/images', function (Request $request) {
    $maxImageKb = max(1, (int) config('waasabi.upload.max_image_mb', 5) * 1024);
    $request->validate([
        'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . $maxImageKb],
    ]);

    $file = $request->file('image');
    if (!$file instanceof UploadedFile) {
        return response()->json(['message' => 'Invalid upload'], 422);
    }

    try {
        $result = processImageUpload($file, [
            'dir' => 'uploads/editor',
            'preview_dir' => 'uploads/editor/previews',
            'preview_side' => 480,
            'max_side' => 2560,
            'max_pixels' => 16000000,
        ]);
    } catch (RuntimeException $exception) {
        return response()->json(['message' => $exception->getMessage()], 422);
    }

    maybeFlagImageForModeration($result['path'], $request->user(), 'editor');

    return response()->json([
        'url' => asset($result['path']),
        'preview_url' => $result['preview'] ? asset($result['preview']) : null,
    ]);
})->middleware(['auth', 'can:publish', 'verified', 'account.age', 'throttle:uploads'])->name('uploads.images');

Route::post('/posts/{slug}/save', function (Request $request, string $slug) {
    if (!safeHasTable('post_saves')) {
        return response()->json(['message' => 'Saves table missing'], 503);
    }
    if (!safeHasTable('posts')) {
        return response()->json(['message' => 'Posts table missing'], 503);
    }

    $post = Post::where('slug', $slug)->firstOrFail();
    if (!canViewHiddenContent($request->user(), $post->user_id)) {
        $isHidden = (bool) ($post->is_hidden ?? false);
        $status = (string) ($post->moderation_status ?? 'approved');
        if ($isHidden || $status !== 'approved') {
            return response()->json(['message' => 'Post not found'], 404);
        }
    }
    $userId = $request->user()->id;
    $exists = DB::table('post_saves')
        ->where('user_id', $userId)
        ->where('post_id', $post->id)
        ->exists();

    if ($exists) {
        DB::table('post_saves')
            ->where('user_id', $userId)
            ->where('post_id', $post->id)
            ->delete();
    } else {
        DB::table('post_saves')->insert([
            'user_id' => $userId,
            'post_id' => $post->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $count = DB::table('post_saves')->where('post_id', $post->id)->count();
    return response()->json(['saved' => !$exists, 'count' => $count]);
})->middleware(['auth', 'verified', 'throttle:post-actions'])->name('posts.save');

Route::post('/posts/{slug}/upvote', function (Request $request, string $slug) {
    if (!safeHasTable('post_upvotes')) {
        return response()->json(['message' => 'Upvotes table missing'], 503);
    }
    if (!safeHasTable('posts')) {
        return response()->json(['message' => 'Posts table missing'], 503);
    }

    $post = Post::where('slug', $slug)->firstOrFail();
    if (!canViewHiddenContent($request->user(), $post->user_id)) {
        $isHidden = (bool) ($post->is_hidden ?? false);
        $status = (string) ($post->moderation_status ?? 'approved');
        if ($isHidden || $status !== 'approved') {
            return response()->json(['message' => 'Post not found'], 404);
        }
    }
    $userId = $request->user()->id;
    $exists = DB::table('post_upvotes')
        ->where('user_id', $userId)
        ->where('post_id', $post->id)
        ->exists();

    if ($exists) {
        DB::table('post_upvotes')
            ->where('user_id', $userId)
            ->where('post_id', $post->id)
            ->delete();
    } else {
        DB::table('post_upvotes')->insert([
            'user_id' => $userId,
            'post_id' => $post->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $author = User::find($post->user_id);
        if ($author) {
            app(MakerPromotionService::class)->maybePromote($author);
        }
    }

    $count = DB::table('post_upvotes')->where('post_id', $post->id)->count();
    return response()->json(['upvoted' => !$exists, 'count' => $count]);
})->middleware(['auth', 'verified', 'throttle:post-actions'])->name('posts.upvote');

Route::post('/reports', [ReportsController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:reports'])
    ->name('reports.store');

Route::get('/publish', [PublishController::class, 'create'])
    ->middleware(['auth', 'can:publish', 'verified', 'account.age'])
    ->name('publish');

Route::get('/posts/{slug}/edit', [PublishController::class, 'edit'])
    ->middleware(['auth', 'verified', 'account.age'])
    ->name('posts.edit');

Route::post('/publish', [PublishController::class, 'store'])
    ->middleware(['auth', 'can:publish', 'verified', 'account.age', 'throttle:publish'])
    ->name('publish.store');

Route::get('/profile', function () use ($projects, $profile, $badgeCatalog) {
    $user = Auth::user();
    if ($user && safeHasColumn('users', 'slug') && empty($user->slug)) {
        $user->slug = $generateUserSlug($user->name ?? 'user');
        $user->save();
    }
    if ($user && !empty($user->slug)) {
        return redirect()->route('profile.show', $user->slug);
    }

    return view('profile', [
        'projects' => $projects,
        'questions' => [],
        'comments' => [],
        'profile_user' => $profile,
        'is_owner' => false,
        'followers_count' => 0,
        'following_count' => 0,
        'is_following' => false,
        'badges' => [],
        'badge_catalog' => $badgeCatalog,
        'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
    ]);
})->name('profile');

Route::get('/profile/settings', [ProfileSettingsController::class, 'edit'])
    ->middleware('auth')
    ->name('profile.settings');

Route::post('/profile/settings', [ProfileSettingsController::class, 'update'])
    ->middleware('auth')
    ->name('profile.settings.update');

Route::post('/profile/{slug}/banner', [ProfileSettingsController::class, 'updateBanner'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:profile-media'])
    ->name('profile.banner.update');

Route::delete('/profile/{slug}/banner', [ProfileSettingsController::class, 'deleteBanner'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:profile-media'])
    ->name('profile.banner.delete');

Route::post('/profile/{slug}/avatar', [ProfileSettingsController::class, 'updateAvatar'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:profile-media'])
    ->name('profile.avatar.update');

Route::delete('/profile/{slug}/avatar', [ProfileSettingsController::class, 'deleteAvatar'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:profile-media'])
    ->name('profile.avatar.delete');

Route::get('/profile/{slug}', function (string $slug) use ($projects, $profile, $mapPostToProject, $mapPostToQuestion, $preparePostStats, $badgeCatalog) {
    $viewer = Auth::user();
    $user = null;
    if (safeHasTable('users')) {
        $user = User::where('slug', $slug)->first();
        if (!$user) {
            $user = User::all()->first(function (User $candidate) use ($slug) {
                return Str::slug($candidate->name ?? '') === $slug;
            });
        }
    }

    if (!$user) {
        $fallbackSlug = Str::slug($profile['name'] ?? '');
        if ($fallbackSlug !== $slug) {
            abort(404);
        }
        return view('profile', [
            'projects' => $projects,
            'questions' => [],
            'comments' => [],
            'profile_user' => $profile,
            'is_owner' => false,
            'followers_count' => 0,
            'following_count' => 0,
            'is_following' => false,
            'badges' => [],
            'badge_catalog' => $badgeCatalog,
            'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
        ]);
    }

    if (safeHasColumn('users', 'slug') && empty($user->slug)) {
        $user->slug = $generateUserSlug($user->name ?? 'user');
        $user->save();
    }

    $isBanned = safeHasColumn('users', 'is_banned') ? (bool) $user->is_banned : false;

    $profileUser = [
        'id' => $user->id,
        'name' => $user->name,
        'slug' => $user->slug,
        'bio' => $user->bio ?? '',
        'role' => $user->roleKey(),
        'avatar' => $user->avatar ?? '/images/avatar-default.svg',
        'banner_url' => safeHasColumn('users', 'banner_url') ? ($user->banner_url ?: null) : null,
        'is_banned' => $isBanned,
        'allow_follow' => safeHasColumn('users', 'connections_allow_follow')
            ? (bool) $user->connections_allow_follow
            : true,
        'show_follow_counts' => safeHasColumn('users', 'connections_show_follow_counts')
            ? (bool) $user->connections_show_follow_counts
            : true,
    ];

    $projectsList = [];
    $questionsList = [];
    if (!$isBanned && safeHasTable('posts')) {
        $userPostsQuery = Post::with(['user', 'editedBy'])->where('user_id', $user->id);
        if (!canViewHiddenContent($viewer, $user->id)) {
            applyVisibilityFilters($userPostsQuery, 'posts', $viewer);
        }
        $userPosts = $userPostsQuery->latest()->get();
        $stats = $preparePostStats($userPosts);
        $projectsList = $userPosts
            ->where('type', 'post')
            ->map(static fn (Post $post) => $mapPostToProject($post, $stats))
            ->values()
            ->all();
        $questionsList = $userPosts
            ->where('type', 'question')
            ->map(static fn (Post $post) => $mapPostToQuestion($post, $stats))
            ->values()
            ->all();
    }

    $commentsList = [];
    if (safeHasTable('post_comments')) {
        $commentQuery = PostComment::with('user')
            ->where('user_id', $user->id);
        if (!canViewHiddenContent($viewer, $user->id)) {
            applyVisibilityFilters($commentQuery, 'post_comments', $viewer);
        }
        $commentRows = $commentQuery
            ->latest()
            ->take(20)
            ->get();

        $postMap = collect();
        if (safeHasTable('posts') && $commentRows->isNotEmpty()) {
            $postMapQuery = Post::whereIn('slug', $commentRows->pluck('post_slug')->all());
            if (!canViewHiddenContent($viewer, $user->id)) {
                applyVisibilityFilters($postMapQuery, 'posts', $viewer);
            }
            $postMap = $postMapQuery
                ->get(['slug', 'title', 'type'])
                ->keyBy('slug');
        }

        $commentsList = $commentRows
            ->map(function (PostComment $comment) use ($postMap) {
                $post = $postMap->get($comment->post_slug);
                return [
                    'body' => $comment->body,
                    'time' => $comment->created_at?->diffForHumans() ?? '',
                    'post_slug' => $comment->post_slug,
                    'post_title' => $post?->title ?? $comment->post_slug,
                    'post_type' => $post?->type ?? 'post',
                ];
            })
            ->values()
            ->all();
    }

    $followersCount = 0;
    $followingCount = 0;
    $isFollowing = false;
    if (safeHasTable('user_follows')) {
        $followersCount = DB::table('user_follows')->where('following_id', $user->id)->count();
        $followingCount = DB::table('user_follows')->where('follower_id', $user->id)->count();
        if (Auth::check()) {
            $isFollowing = DB::table('user_follows')
                ->where('following_id', $user->id)
                ->where('follower_id', Auth::id())
                ->exists();
        }
    }

    $badges = userBadgesPayload($user, $badgeCatalog);

    return view('profile', [
        'projects' => $projectsList,
        'questions' => $questionsList,
        'comments' => $commentsList,
        'profile_user' => $profileUser,
        'is_owner' => Auth::id() === $user->id,
        'followers_count' => $followersCount,
        'following_count' => $followingCount,
        'is_following' => $isFollowing,
        'badges' => $badges,
        'badge_catalog' => $badgeCatalog,
        'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
    ]);
})->name('profile.show');

Route::post('/profile/{slug}/badges', function (Request $request, string $slug) use ($badgeCatalog) {
    $viewer = $request->user();
    if (!$viewer || !$viewer->isAdmin()) {
        abort(403);
    }
    if (!safeHasTable('users')) {
        abort(503);
    }

    $data = $request->validate([
        'badge_key' => ['required', 'string', 'max:100'],
        'name' => ['nullable', 'string', 'max:120'],
        'description' => ['nullable', 'string', 'max:1000'],
        'reason' => ['nullable', 'string', 'max:255'],
    ]);

    $user = User::where('slug', $slug)->firstOrFail();
    $badgeKey = $data['badge_key'];
    $catalogMap = collect($badgeCatalog)->keyBy('key');
    $catalog = $catalogMap->get($badgeKey);
    if (!$catalog) {
        return response()->json(['message' => 'Unknown badge'], 422);
    }

    try {
        $badge = $user->grantBadge($badgeKey, [
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'reason' => $data['reason'] ?? null,
            'issued_by' => $viewer,
            'issued_at' => now(),
            'catalog' => $catalog,
            'link' => route('profile.show', $user->slug),
        ], true);
    } catch (\InvalidArgumentException $exception) {
        return response()->json(['message' => 'Unknown badge'], 422);
    } catch (\RuntimeException $exception) {
        abort(503);
    }

    $badges = userBadgesPayload($user->fresh(), $badgeCatalog);
    $badgePayload = collect($badges)->firstWhere('id', $badge->id);

    return response()->json([
        'ok' => true,
        'badge_id' => $badge->id,
        'badge' => $badgePayload,
        'badges' => $badges,
    ]);
})->middleware(['auth', 'throttle:10,1'])->name('profile.badges.grant');

Route::delete('/profile/{slug}/badges/{badgeId}', function (Request $request, string $slug, int $badgeId) use ($badgeCatalog) {
    $viewer = $request->user();
    if (!$viewer || !$viewer->isAdmin()) {
        abort(403);
    }
    if (!safeHasTable('user_badges')) {
        abort(503);
    }

    $user = User::where('slug', $slug)->firstOrFail();
    $deleted = $user->revokeBadge($badgeId);

    if (!$deleted) {
        return response()->json(['message' => 'Badge not found'], 404);
    }

    $badges = userBadgesPayload($user->fresh(), $badgeCatalog);

    return response()->json([
        'ok' => true,
        'badge_id' => $badgeId,
        'badges' => $badges,
    ]);
})->middleware(['auth', 'throttle:10,1'])->name('profile.badges.revoke');

Route::post('/profile/{slug}/follow', function (Request $request, string $slug) {
    $viewer = $request->user();
    if (!$viewer) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return redirect()->route('login');
    }
    if (!safeHasTable('user_follows')) {
        abort(503);
    }
    $user = User::where('slug', $slug)->firstOrFail();
    if ($user->id === $viewer->id) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Cannot follow yourself'], 400);
        }
        return redirect()->back();
    }
    if (safeHasColumn('users', 'connections_allow_follow') && !$user->connections_allow_follow) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Follow disabled'], 403);
        }
        return redirect()->back();
    }

    $existing = DB::table('user_follows')
        ->where('follower_id', $viewer->id)
        ->where('following_id', $user->id)
        ->exists();

    if ($existing) {
        DB::table('user_follows')
            ->where('follower_id', $viewer->id)
            ->where('following_id', $user->id)
            ->delete();
    } else {
        DB::table('user_follows')->insert([
            'follower_id' => $viewer->id,
            'following_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    if ($request->expectsJson()) {
        $followersCount = DB::table('user_follows')->where('following_id', $user->id)->count();
        $followingCount = DB::table('user_follows')->where('follower_id', $user->id)->count();
        return response()->json([
            'is_following' => !$existing,
            'followers_count' => $followersCount,
            'following_count' => $followingCount,
        ]);
    }

    return redirect()->back();
})->middleware(['auth', 'verified', 'throttle:profile-follow'])->name('profile.follow');

Route::get('/showcase', function () use ($showcase) {
    return view('showcase', ['showcase' => $showcase, 'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload()]);
})->name('showcase');

Route::get('/notifications', [NotificationsController::class, 'index'])
    ->middleware('auth')
    ->name('notifications');
Route::post('/notifications/{notification}/read', [NotificationsController::class, 'markRead'])
    ->middleware('auth')
    ->name('notifications.read');
Route::post('/notifications/read-all', [NotificationsController::class, 'markAllRead'])
    ->middleware('auth')
    ->name('notifications.read_all');

Route::get('/support/kb/{slug}', [SupportController::class, 'kb'])->name('support.kb');
Route::get('/support/docs/{slug}', [SupportController::class, 'docs'])->name('support.docs');
Route::get('/support', [SupportController::class, 'index'])->name('support');
Route::get('/support/tickets/new', [SupportController::class, 'ticketNew'])
    ->middleware('auth')
    ->name('support.ticket');
Route::post('/support/tickets', [SupportTicketController::class, 'store'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:support-ticket'])
    ->name('support.ticket.store');
Route::post('/support/tickets/{ticket}/messages', [SupportTicketController::class, 'storeMessage'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:support-message'])
    ->name('support.ticket.message');

Route::get('/locale/{locale}', function (string $locale, Request $request) {
    if (!in_array($locale, ['en', 'fi'], true)) {
        abort(400);
    }

    $request->session()->put('locale', $locale);

    return redirect()->back();
})->name('locale');

Route::get('/login', function () {
    return view('auth.login', ['current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload()]);
})->name('login');

Route::get('/register', function () {
    return view('auth.register', ['current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload()]);
})->name('register');

Route::get('/verify-email', function () {
    return view('auth.verify-email', ['current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload()]);
})->middleware('auth')->name('verification.notice');

Route::post('/login', function (Request $request) {
    if (honeypotTripped($request)) {
        logAuditEvent($request, 'auth.honeypot', null, ['context' => 'login']);
        return back()->withErrors([
            'email' => __('ui.auth.captcha_failed'),
        ])->onlyInput('email');
    }
    if (captchaEnabled('login') && !verifyCaptcha($request)) {
        logAuditEvent($request, 'auth.captcha_failed', null, ['context' => 'login']);
        return back()->withErrors([
            'email' => __('ui.auth.captcha_failed'),
        ])->onlyInput('email');
    }

    $credentials = $request->validate([
        'email' => ['required', 'string', 'email', 'max:255'],
        'password' => ['required'],
    ]);
    $credentials['email'] = strtolower((string) ($credentials['email'] ?? ''));

    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();
        $user = Auth::user();
        if ($user && safeHasColumn('users', 'is_banned') && $user->is_banned) {
            logAuditEvent($request, 'auth.login_banned', $user);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors([
                'email' => __('ui.auth.banned'),
            ])->onlyInput('email');
        }
        if ($user) {
            logAuditEvent($request, 'auth.login', $user, [
                'email_hash' => hash('sha256', strtolower((string) $user->email)),
            ]);
        }
        return redirect()->route('feed');
    }

    if (!empty($credentials['email'])) {
        logAuditEvent($request, 'auth.login_failed', null, [
            'email_hash' => hash('sha256', strtolower((string) $credentials['email'])),
        ]);
    }
    return back()->withErrors([
        'email' => 'Invalid email or password.',
    ])->onlyInput('email');
})->middleware('throttle:login')->name('login.store');

Route::post('/register', function (Request $request) use ($generateUserSlug) {
    if (honeypotTripped($request)) {
        logAuditEvent($request, 'auth.honeypot', null, ['context' => 'register']);
        return back()->withErrors([
            'email' => __('ui.auth.captcha_failed'),
        ])->onlyInput('email');
    }
    if (captchaEnabled('register') && !verifyCaptcha($request)) {
        logAuditEvent($request, 'auth.captcha_failed', null, ['context' => 'register']);
        return back()->withErrors([
            'email' => __('ui.auth.captcha_failed'),
        ])->onlyInput('email');
    }

    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()->uncompromised()],
        'accept_legal' => ['accepted'],
    ]);

    $role = User::count() === 0 ? 'admin' : 'user';
    $slug = $generateUserSlug($data['name']);

    $user = User::create([
        'name' => $data['name'],
        'slug' => $slug,
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        'role' => $role,
    ]);

    event(new Registered($user));
    Auth::login($user);
    $request->session()->regenerate();

    logAuditEvent($request, 'auth.register', $user, [
        'email_hash' => hash('sha256', strtolower((string) $user->email)),
    ]);

    return redirect()->route('verification.notice');
})->middleware('throttle:register')->name('register.store');

Route::get('/verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    $user = $request->user();
    if ($user) {
        logAuditEvent($request, 'auth.verify', $user);
    }
    return redirect()->route('feed')->with('toast', __('ui.auth.verify_success'));
})->middleware(['auth', 'signed', 'throttle:verification'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    if (honeypotTripped($request)) {
        logAuditEvent($request, 'auth.honeypot', $request->user(), ['context' => 'verification']);
        return back()->with('toast', __('ui.auth.captcha_failed'));
    }
    if (captchaEnabled('verification') && !verifyCaptcha($request)) {
        logAuditEvent($request, 'auth.captcha_failed', $request->user(), ['context' => 'verification']);
        return back()->with('toast', __('ui.auth.captcha_failed'));
    }
    $user = $request->user();
    if ($user && $user->hasVerifiedEmail()) {
        return back()->with('toast', __('ui.auth.verify_already'));
    }
    $user?->sendEmailVerificationNotification();
    if ($user) {
        logAuditEvent($request, 'auth.verify_resend', $user);
    }
    return back()->with('toast', __('ui.auth.verify_sent'));
})->middleware(['auth', 'throttle:verification'])->name('verification.send');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('feed');
})->name('logout');

Route::delete('/posts/{post}', function (Request $request, Post $post) {
    $user = $request->user();
    if (!$user) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return redirect()->route('login');
    }
    if ($user->cannot('delete', $post)) {
        abort(403);
    }

    $post->delete();

    if ($request->expectsJson()) {
        return response()->json(['ok' => true]);
    }

    return redirect()->route('feed');
})->middleware('auth')->name('posts.delete');

Route::get('/read-later', function () use ($projects, $mapPostToProject, $mapPostToQuestion, $preparePostStats) {
    $user = Auth::user();
    $savedItems = [];
    if ($user && safeHasTable('post_saves') && safeHasTable('posts')) {
        $savedIds = DB::table('post_saves')
            ->where('user_id', $user->id)
            ->pluck('post_id')
            ->toArray();
        $savedPostsQuery = Post::with(['user', 'editedBy'])
            ->whereIn('id', $savedIds);
        applyVisibilityFilters($savedPostsQuery, 'posts', $user);
        $savedPosts = $savedPostsQuery
            ->latest()
            ->get();
        $stats = $preparePostStats($savedPosts);
        $savedItems = $savedPosts
            ->map(static function (Post $post) use ($mapPostToProject, $mapPostToQuestion, $stats) {
                if ($post->type === 'question') {
                    return [
                        'type' => 'question',
                        'data' => $mapPostToQuestion($post, $stats),
                    ];
                }
                return [
                    'type' => 'project',
                    'data' => $mapPostToProject($post, $stats),
                ];
            })
            ->values()
            ->all();
    }
    return view('read-later', ['items' => $savedItems, 'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload()]);
})->middleware('auth')->name('read-later');

Route::get('/read-later/list', function (Request $request) {
    $user = $request->user();
    if (!$user) {
        return response()->json(['items' => []], 401);
    }
    if (!safeHasTable('post_saves') || !safeHasTable('posts')) {
        return response()->json(['items' => []], 503);
    }

    $items = DB::table('post_saves')
        ->join('posts', 'posts.id', '=', 'post_saves.post_id')
        ->where('post_saves.user_id', $user->id)
        ->orderByDesc('post_saves.created_at')
        ->limit(200)
        ->pluck('posts.slug')
        ->filter()
        ->values()
        ->all();

    return response()->json(['items' => $items]);
})->middleware('auth')->name('read-later.list');

Route::post('/read-later/sync', function (Request $request) {
    $user = $request->user();
    if (!$user) {
        return response()->json(['items' => []], 401);
    }
    if (!safeHasTable('post_saves') || !safeHasTable('posts')) {
        return response()->json(['items' => []], 503);
    }

    $rawItems = $request->input('items', []);
    if (!is_array($rawItems)) {
        return response()->json(['message' => 'Invalid payload'], 422);
    }

    $slugs = collect($rawItems)
        ->map(static fn ($slug) => Str::of((string) $slug)->trim()->lower()->toString())
        ->filter(static fn ($slug) => $slug !== '')
        ->unique()
        ->take(400)
        ->values();

    if ($slugs->isNotEmpty()) {
        $postsQuery = Post::query()
            ->whereIn('slug', $slugs->all())
            ->select('posts.id', 'posts.slug');
        applyVisibilityFilters($postsQuery, 'posts', $user);
        $posts = $postsQuery->get();

        if ($posts->isNotEmpty()) {
            $postIds = $posts->pluck('id')->all();
            $existingIds = DB::table('post_saves')
                ->where('user_id', $user->id)
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->all();

            $missingIds = array_values(array_diff($postIds, $existingIds));
            if ($missingIds) {
                $now = now();
                $rows = array_map(static fn (int $postId) => [
                    'user_id' => $user->id,
                    'post_id' => $postId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $missingIds);

                DB::table('post_saves')->insert($rows);
            }
        }
    }

    $savedQuery = Post::query()
        ->join('post_saves', 'post_saves.post_id', '=', 'posts.id')
        ->where('post_saves.user_id', $user->id)
        ->orderByDesc('post_saves.created_at')
        ->select('posts.slug');
    applyVisibilityFilters($savedQuery, 'posts', $user);

    $items = $savedQuery
        ->limit(400)
        ->pluck('posts.slug')
        ->filter()
        ->values()
        ->all();

    return response()->json(['items' => $items]);
})->middleware(['auth', 'throttle:read-later'])->name('read-later.sync');

Route::get('/read-later/render', function () use ($mapPostToProject, $mapPostToQuestion, $preparePostStats) {
    $user = Auth::user();
    if (!$user) {
        return response()->json(['items' => [], 'slugs' => []], 401);
    }
    if (!safeHasTable('post_saves') || !safeHasTable('posts')) {
        return response()->json(['items' => [], 'slugs' => []], 503);
    }

    $savedPostsQuery = Post::with(['user', 'editedBy'])
        ->join('post_saves', 'post_saves.post_id', '=', 'posts.id')
        ->where('post_saves.user_id', $user->id)
        ->orderByDesc('post_saves.created_at')
        ->select('posts.*')
        ->distinct();
    applyVisibilityFilters($savedPostsQuery, 'posts', $user);

    $savedPosts = $savedPostsQuery->get();
    $stats = $preparePostStats($savedPosts);

    $items = $savedPosts
        ->map(static function (Post $post) use ($mapPostToProject, $mapPostToQuestion, $stats) {
            if ($post->type === 'question') {
                $question = $mapPostToQuestion($post, $stats);
                return view('partials.question-card', ['question' => $question])->render();
            }
            $project = $mapPostToProject($post, $stats);
            return view('partials.project-card', ['project' => $project])->render();
        })
        ->values()
        ->all();

    $slugs = $savedPosts
        ->pluck('slug')
        ->filter()
        ->unique()
        ->values()
        ->all();

    return response()->json([
        'items' => $items,
        'slugs' => $slugs,
    ]);
})->middleware(['auth', 'throttle:read-later'])->name('read-later.render');

Route::middleware(['auth', 'can:moderate'])->group(function () {
    Route::get('/admin', function (Request $request) {
        $perPage = 20;
        $search = trim((string) $request->query('q', ''));
        $like = $search !== '' ? '%' . $search . '%' : null;
        $moderationSort = (string) $request->query('sort', 'reporters');
        $moderationSort = in_array($moderationSort, ['reporters', 'recent'], true) ? $moderationSort : 'reporters';

        $users = User::query()
            ->when($search !== '', function ($query) use ($like) {
                $query->where(function ($subQuery) use ($like) {
                    $subQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('slug', 'like', $like);
                });
            })
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'users_page');
        $comments = safeHasTable('post_comments')
            ? PostComment::with('user')
                ->latest()
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery
                            ->where('body', 'like', $like)
                            ->orWhere('post_slug', 'like', $like)
                            ->orWhereHas('user', function ($userQuery) use ($like) {
                                $userQuery
                                    ->where('name', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhere('slug', 'like', $like);
                            });
                    });
                })
                ->paginate($perPage, ['*'], 'comments_page')
            : collect();
        $reviews = safeHasTable('post_reviews')
            ? PostReview::with('user')
                ->latest()
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery
                            ->where('improve', 'like', $like)
                            ->orWhere('why', 'like', $like)
                            ->orWhere('how', 'like', $like)
                            ->orWhere('post_slug', 'like', $like)
                            ->orWhereHas('user', function ($userQuery) use ($like) {
                                $userQuery
                                    ->where('name', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhere('slug', 'like', $like);
                            });
                    });
                })
                ->paginate($perPage, ['*'], 'reviews_page')
            : collect();
        $reportedPosts = collect();
        if (safeHasTable('content_reports') && safeHasTable('posts')) {
            $reportsHaveWeight = safeHasColumn('content_reports', 'weight');
            $weightSelect = $reportsHaveWeight
                ? DB::raw('coalesce(sum(weight), 0) as weight_total')
                : DB::raw('count(*) as weight_total');
            $reportRowsQuery = DB::table('content_reports')
                ->select('content_id', DB::raw('count(*) as report_count'), $weightSelect)
                ->where('content_type', 'post')
                ->whereNotNull('content_id');

            if ($search !== '') {
                $matchedPostIds = Post::query()
                    ->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->pluck('id');
                $matchedPostSlugs = Post::query()
                    ->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->pluck('slug');

                $reportRowsQuery->where(function ($subQuery) use ($like, $matchedPostIds, $matchedPostSlugs) {
                    if ($matchedPostIds->isNotEmpty()) {
                        $subQuery->orWhereIn('content_id', $matchedPostIds->map(fn ($id) => (string) $id)->all());
                    }
                    if ($matchedPostSlugs->isNotEmpty()) {
                        $subQuery->orWhereIn('content_id', $matchedPostSlugs->all());
                    }
                    if (safeHasColumn('content_reports', 'details')) {
                        $subQuery->orWhere('details', 'like', $like);
                    }
                });
            }

            $reportRows = $reportRowsQuery
                ->groupBy('content_id')
                ->orderByDesc($reportsHaveWeight ? 'weight_total' : 'report_count')
                ->orderByDesc('report_count')
                ->paginate($perPage, ['*'], 'reports_page');

            $contentIds = collect($reportRows->items())
                ->map(fn ($row) => (string) $row->content_id)
                ->filter()
                ->unique()
                ->values();

            $numericIds = $contentIds->filter(fn ($id) => ctype_digit($id))->values();
            $slugIds = $contentIds->reject(fn ($id) => ctype_digit($id))->values();

            $postsById = $numericIds->isNotEmpty()
                ? Post::query()->whereIn('id', $numericIds->map(fn ($id) => (int) $id)->all())->get()->keyBy('id')
                : collect();
            $postsBySlug = $slugIds->isNotEmpty()
                ? Post::query()->whereIn('slug', $slugIds->all())->get()->keyBy('slug')
                : collect();

            $detailRows = $contentIds->isNotEmpty()
                ? DB::table('content_reports')
                    ->select('content_id', 'details', 'created_at')
                    ->where('content_type', 'post')
                    ->whereIn('content_id', $contentIds->all())
                    ->whereNotNull('details')
                    ->where('details', '<>', '')
                    ->orderByDesc('created_at')
                    ->get()
                : collect();

            $detailsById = $detailRows->groupBy('content_id')->map(function ($rows) {
                return $rows->first();
            });

            $mappedReports = collect($reportRows->items())
                ->map(function ($row) use ($postsById, $postsBySlug, $detailsById, $reportsHaveWeight) {
                    $contentId = (string) $row->content_id;
                    $post = ctype_digit($contentId)
                        ? $postsById->get((int) $contentId)
                        : $postsBySlug->get($contentId);
                    if (!$post) {
                        return null;
                    }
                    $detail = $detailsById->get($contentId);
                    $pointsRaw = $reportsHaveWeight ? (float) ($row->weight_total ?? 0) : (float) ($row->report_count ?? 0);
                    $points = $reportsHaveWeight ? round($pointsRaw, 1) : (int) $pointsRaw;
                    return [
                        'post' => $post,
                        'count' => (int) $row->report_count,
                        'points' => $points,
                        'details' => $detail?->details,
                        'reported_at' => $detail?->created_at,
                    ];
                })
                ->filter()
                ->values();
            $reportRows->setCollection($mappedReports);
            $reportedPosts = $reportRows;
        }

        $mediaReports = collect();
        if (safeHasTable('content_reports')) {
            $mediaReports = ContentReport::with('user')
                ->where('content_type', 'content')
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery
                            ->where('content_url', 'like', $like)
                            ->orWhere('details', 'like', $like);
                    });
                })
                ->latest()
                ->paginate($perPage, ['*'], 'media_page');
        }

        $moderationFeed = collect();
        if (safeHasTable('content_reports')) {
            $reportTypes = ['post', 'question', 'comment', 'review'];
            $reportsHaveWeight = safeHasColumn('content_reports', 'weight');
            $weightSelect = $reportsHaveWeight
                ? DB::raw('coalesce(sum(weight), 0) as weight_total')
                : DB::raw('count(*) as weight_total');
            $reportQuery = DB::table('content_reports')
                ->select(
                    'content_type',
                    'content_id',
                    DB::raw('count(*) as report_count'),
                    DB::raw('count(distinct coalesce(user_id, id)) as reporters_count'),
                    DB::raw('max(created_at) as last_report_at'),
                    DB::raw('max(content_url) as content_url'),
                    $weightSelect,
                )
                ->whereIn('content_type', $reportTypes)
                ->whereNotNull('content_id')
                ->where('content_id', '<>', '');

            if ($search !== '' && $like !== null) {
                $postMatches = collect();
                $questionMatches = collect();
                if (safeHasTable('posts')) {
                    $postMatches = Post::query()
                        ->where('type', 'post')
                        ->where(function ($query) use ($like) {
                            $query
                                ->where('title', 'like', $like)
                                ->orWhere('slug', 'like', $like)
                                ->orWhere('subtitle', 'like', $like);
                        })
                        ->get(['id', 'slug']);
                    $questionMatches = Post::query()
                        ->where('type', 'question')
                        ->where(function ($query) use ($like) {
                            $query
                                ->where('title', 'like', $like)
                                ->orWhere('slug', 'like', $like)
                                ->orWhere('subtitle', 'like', $like);
                        })
                        ->get(['id', 'slug']);
                }

                $commentMatchIds = safeHasTable('post_comments')
                    ? PostComment::query()
                        ->where(function ($query) use ($like) {
                            $query
                                ->where('body', 'like', $like)
                                ->orWhere('post_slug', 'like', $like);
                        })
                        ->pluck('id')
                        ->map(fn ($id) => (string) $id)
                    : collect();

                $reviewMatchIds = safeHasTable('post_reviews')
                    ? PostReview::query()
                        ->where(function ($query) use ($like) {
                            $query
                                ->where('improve', 'like', $like)
                                ->orWhere('why', 'like', $like)
                                ->orWhere('how', 'like', $like)
                                ->orWhere('post_slug', 'like', $like);
                        })
                        ->pluck('id')
                        ->map(fn ($id) => (string) $id)
                    : collect();

                $postMatchIds = $postMatches->pluck('id')->map(fn ($id) => (string) $id)->filter();
                $postMatchSlugs = $postMatches->pluck('slug')->filter();
                $questionMatchIds = $questionMatches->pluck('id')->map(fn ($id) => (string) $id)->filter();
                $questionMatchSlugs = $questionMatches->pluck('slug')->filter();

                $reportQuery->where(function ($query) use ($like, $postMatchIds, $postMatchSlugs, $questionMatchIds, $questionMatchSlugs, $commentMatchIds, $reviewMatchIds) {
                    $query
                        ->where('content_url', 'like', $like)
                        ->orWhere('details', 'like', $like)
                        ->orWhere('content_id', 'like', $like);

                    if ($postMatchIds->isNotEmpty() || $postMatchSlugs->isNotEmpty()) {
                        $postMatches = $postMatchIds->merge($postMatchSlugs)->unique()->values()->all();
                        $query->orWhere(function ($subQuery) use ($postMatches) {
                            $subQuery->where('content_type', 'post')->whereIn('content_id', $postMatches);
                        });
                    }
                    if ($questionMatchIds->isNotEmpty() || $questionMatchSlugs->isNotEmpty()) {
                        $questionMatches = $questionMatchIds->merge($questionMatchSlugs)->unique()->values()->all();
                        $query->orWhere(function ($subQuery) use ($questionMatches) {
                            $subQuery->where('content_type', 'question')->whereIn('content_id', $questionMatches);
                        });
                    }
                    if ($commentMatchIds->isNotEmpty()) {
                        $query->orWhere(function ($subQuery) use ($commentMatchIds) {
                            $subQuery->where('content_type', 'comment')->whereIn('content_id', $commentMatchIds->all());
                        });
                    }
                    if ($reviewMatchIds->isNotEmpty()) {
                        $query->orWhere(function ($subQuery) use ($reviewMatchIds) {
                            $subQuery->where('content_type', 'review')->whereIn('content_id', $reviewMatchIds->all());
                        });
                    }
                });
            }

            $reportQuery->groupBy('content_type', 'content_id');

            if ($moderationSort === 'reporters') {
                $reportQuery
                    ->orderByDesc('reporters_count')
                    ->orderByDesc($reportsHaveWeight ? 'weight_total' : 'report_count')
                    ->orderByDesc('last_report_at');
            } else {
                $reportQuery
                    ->orderByDesc($reportsHaveWeight ? 'weight_total' : 'report_count')
                    ->orderByDesc('last_report_at');
            }

            $moderationFeed = $reportQuery->paginate($perPage, ['*'], 'moderation_page');
            $reportRows = collect($moderationFeed->items());
            $detailsByKey = collect();
            if (safeHasColumn('content_reports', 'details') && $reportRows->isNotEmpty()) {
                $detailContentIds = $reportRows->pluck('content_id')->filter()->unique()->values();
                if ($detailContentIds->isNotEmpty()) {
                    $detailRows = DB::table('content_reports')
                        ->select('content_type', 'content_id', 'details', 'created_at')
                        ->whereIn('content_type', $reportTypes)
                        ->whereIn('content_id', $detailContentIds->all())
                        ->whereNotNull('details')
                        ->where('details', '<>', '')
                        ->orderByDesc('created_at')
                        ->get();
                    $detailsByKey = $detailRows->groupBy(fn ($row) => $row->content_type . ':' . $row->content_id)
                        ->map(fn ($rows) => $rows->first());
                }
            }

            $postContentIds = $reportRows
                ->filter(fn ($row) => in_array($row->content_type, ['post', 'question'], true))
                ->pluck('content_id')
                ->filter()
                ->values();
            $numericPostIds = $postContentIds->filter(fn ($id) => ctype_digit((string) $id))->map(fn ($id) => (int) $id)->values();
            $slugPostIds = $postContentIds->reject(fn ($id) => ctype_digit((string) $id))->values();

            $posts = collect();
            if (safeHasTable('posts') && ($numericPostIds->isNotEmpty() || $slugPostIds->isNotEmpty())) {
                $posts = Post::with(['user', 'editedBy'])
                    ->where(function ($query) use ($numericPostIds, $slugPostIds) {
                        if ($numericPostIds->isNotEmpty()) {
                            $query->whereIn('id', $numericPostIds->all());
                        }
                        if ($slugPostIds->isNotEmpty()) {
                            $query->orWhereIn('slug', $slugPostIds->all());
                        }
                    })
                    ->get();
            }

            $postsById = $posts->keyBy('id');
            $postsBySlug = $posts->keyBy('slug');
            $stats = $posts->isNotEmpty()
                ? FeedService::preparePostStats($posts, $request->user())
                : [];

            $commentIds = $reportRows
                ->where('content_type', 'comment')
                ->pluck('content_id')
                ->filter(fn ($id) => ctype_digit((string) $id))
                ->map(fn ($id) => (int) $id)
                ->values();
            $reviewIds = $reportRows
                ->where('content_type', 'review')
                ->pluck('content_id')
                ->filter(fn ($id) => ctype_digit((string) $id))
                ->map(fn ($id) => (int) $id)
                ->values();

            $comments = $commentIds->isNotEmpty() && safeHasTable('post_comments')
                ? PostComment::with('user')->whereIn('id', $commentIds->all())->get()->keyBy('id')
                : collect();
            $reviews = $reviewIds->isNotEmpty() && safeHasTable('post_reviews')
                ? PostReview::with('user')->whereIn('id', $reviewIds->all())->get()->keyBy('id')
                : collect();

            $contextPostSlugs = collect()
                ->merge($comments->pluck('post_slug'))
                ->merge($reviews->pluck('post_slug'))
                ->filter()
                ->unique()
                ->values();
            $contextPosts = $contextPostSlugs->isNotEmpty() && safeHasTable('posts')
                ? Post::query()
                    ->whereIn('slug', $contextPostSlugs->all())
                    ->get(['id', 'slug', 'title', 'type', 'user_id'])
                    ->keyBy('slug')
                : collect();

            $moderationItems = $reportRows
                ->map(function ($row) use ($postsById, $postsBySlug, $stats, $comments, $reviews, $contextPosts, $detailsByKey, $reportsHaveWeight) {
                    $contentId = (string) $row->content_id;
                    $reportCount = (int) ($row->report_count ?? 0);
                    $reportersCount = (int) ($row->reporters_count ?? 0);
                    if ($reportsHaveWeight) {
                        $reportPoints = round((float) ($row->weight_total ?? 0), 1);
                    } else {
                        $reportPoints = $reportersCount > 0 ? $reportersCount : $reportCount;
                    }
                    $lastReportedAt = $row->last_report_at ?? null;
                    $contentUrl = $row->content_url ?? null;
                    $detailKey = $row->content_type . ':' . $contentId;
                    $detailRow = $detailsByKey->get($detailKey);
                    $detailText = is_object($detailRow) ? (string) ($detailRow->details ?? '') : '';
                    $moderationNsfwPending = false;
                    if ($detailText !== '') {
                        $detailLower = Str::lower($detailText);
                        $moderationNsfwPending = Str::contains($detailLower, 'rekognition')
                            && Str::contains($detailLower, 'unavailable');
                    }

                    if (in_array($row->content_type, ['post', 'question'], true)) {
                        $post = ctype_digit($contentId)
                            ? $postsById->get((int) $contentId)
                            : $postsBySlug->get($contentId);
                        if (!$post) {
                            return null;
                        }
                        $data = $post->type === 'question'
                            ? FeedService::mapPostToQuestionWithStats($post, $stats)
                            : FeedService::mapPostToProjectWithStats($post, $stats);
                        $data['report_count'] = $reportCount;
                        $data['report_points'] = $reportPoints;
                        $data['reporters_count'] = $reportersCount;
                        $data['last_report_at'] = $lastReportedAt;
                        $data['moderation_nsfw_pending'] = $moderationNsfwPending;

                        return [
                            'type' => $post->type === 'question' ? 'question' : 'project',
                            'data' => $data,
                        ];
                    }

                    if ($row->content_type === 'comment') {
                        $comment = $comments->get((int) $contentId);
                        if (!$comment) {
                            return null;
                        }
                        $author = $comment->user;
                        $post = $contextPosts->get($comment->post_slug);
                        $postUrl = $post
                            ? ($post->type === 'question' ? route('questions.show', $comment->post_slug) : route('project', $comment->post_slug))
                            : ($comment->post_slug ? resolvePostUrl($comment->post_slug) : $contentUrl);

                        return [
                            'type' => 'comment',
                            'data' => [
                                'id' => $comment->id,
                                'text' => $comment->body,
                                'section' => $comment->section,
                                'time' => $comment->created_at?->diffForHumans() ?? '',
                                'author' => [
                                    'name' => $author?->name ?? __('ui.project.anonymous'),
                                    'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                                    'role' => $author?->role ?? 'user',
                                    'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                                ],
                                'post_slug' => $comment->post_slug,
                                'post_title' => $post?->title ?? $comment->post_slug,
                                'post_url' => $postUrl,
                                'report_count' => $reportCount,
                                'report_points' => $reportPoints,
                                'reporters_count' => $reportersCount,
                                'last_report_at' => $lastReportedAt,
                                'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                                'is_hidden' => (bool) ($comment->is_hidden ?? false),
                            ],
                        ];
                    }

                    if ($row->content_type === 'review') {
                        $review = $reviews->get((int) $contentId);
                        if (!$review) {
                            return null;
                        }
                        $author = $review->user;
                        $post = $contextPosts->get($review->post_slug);
                        $postUrl = $post
                            ? ($post->type === 'question' ? route('questions.show', $review->post_slug) : route('project', $review->post_slug))
                            : ($review->post_slug ? resolvePostUrl($review->post_slug) : $contentUrl);

                        return [
                            'type' => 'review',
                            'data' => [
                                'id' => $review->id,
                                'improve' => $review->improve,
                                'why' => $review->why,
                                'how' => $review->how,
                                'time' => $review->created_at?->diffForHumans() ?? '',
                                'author' => [
                                    'name' => $author?->name ?? __('ui.project.anonymous'),
                                    'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                                    'role' => $author?->role ?? 'user',
                                    'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                                ],
                                'post_slug' => $review->post_slug,
                                'post_title' => $post?->title ?? $review->post_slug,
                                'post_url' => $postUrl,
                                'report_count' => $reportCount,
                                'report_points' => $reportPoints,
                                'reporters_count' => $reportersCount,
                                'last_report_at' => $lastReportedAt,
                                'moderation_status' => (string) ($review->moderation_status ?? 'approved'),
                                'is_hidden' => (bool) ($review->is_hidden ?? false),
                            ],
                        ];
                    }

                    return null;
                })
                ->filter()
                ->values();

            $moderationFeed->setCollection($moderationItems);
        }

        $moderationLogs = collect();
        if (safeHasTable('moderation_logs')) {
            $moderationLogs = ModerationLog::query()
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery
                            ->where('moderator_name', 'like', $like)
                            ->orWhere('moderator_role', 'like', $like)
                            ->orWhere('action', 'like', $like)
                            ->orWhere('content_type', 'like', $like)
                            ->orWhere('content_id', 'like', $like)
                            ->orWhere('content_url', 'like', $like)
                            ->orWhere('notes', 'like', $like)
                            ->orWhere('ip_address', 'like', $like)
                            ->orWhere('location', 'like', $like);
                    });
                })
                ->latest()
                ->paginate($perPage, ['*'], 'moderation_log_page');
        }

        $supportTickets = collect();
        if (safeHasTable('support_tickets')) {
            $supportTickets = SupportTicket::query()
                ->with(['user', 'respondedBy'])
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery
                            ->where('subject', 'like', $like)
                            ->orWhere('body', 'like', $like)
                            ->orWhereHas('user', function ($userQuery) use ($like) {
                                $userQuery
                                    ->where('name', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhere('slug', 'like', $like);
                            });
                    });
                })
                ->orderByRaw("case status when 'open' then 0 when 'waiting' then 1 when 'answered' then 1 when 'closed' then 2 else 3 end")
                ->orderByDesc('updated_at')
                ->paginate($perPage, ['*'], 'support_page');
        }

        $topbarPromos = safeHasTable('topbar_promos')
            ? TopbarPromo::query()->orderBy('sort_order')->orderBy('id')->get()
            : collect();

        return view('admin.index', [
            'users' => $users,
            'comments' => $comments,
            'reviews' => $reviews,
            'reported_posts' => $reportedPosts,
            'media_reports' => $mediaReports,
            'moderation_feed' => $moderationFeed,
            'moderation_logs' => $moderationLogs,
            'moderation_sort' => $moderationSort,
            'support_tickets' => $supportTickets,
            'topbar_promos' => $topbarPromos,
            'admin_search' => $search,
            'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
        ]);
    })->name('admin');

    Route::post('/admin/support-tickets/{ticket}/respond', [AdminSupportController::class, 'respond'])
        ->name('admin.support-tickets.respond');

    Route::post('/admin/users/{user}/ban', [AdminUserController::class, 'toggleBan'])
        ->name('admin.users.ban');

    Route::post('/admin/moderation/posts/{post}/queue', [ModerationController::class, 'queuePost'])
        ->name('moderation.posts.queue');

    Route::post('/admin/moderation/posts/{post}/hide', [ModerationController::class, 'hidePost'])
        ->name('moderation.posts.hide');

    Route::post('/admin/moderation/posts/{post}/restore', [ModerationController::class, 'restorePost'])
        ->name('moderation.posts.restore');

    Route::post('/admin/moderation/posts/{post}/nsfw', [ModerationController::class, 'nsfwPost'])
        ->name('moderation.posts.nsfw');

    Route::post('/admin/moderation/comments/{comment}/queue', [ModerationController::class, 'queueComment'])
        ->name('moderation.comments.queue');

    Route::post('/admin/moderation/comments/{comment}/hide', [ModerationController::class, 'hideComment'])
        ->name('moderation.comments.hide');

    Route::post('/admin/moderation/comments/{comment}/restore', [ModerationController::class, 'restoreComment'])
        ->name('moderation.comments.restore');

    Route::post('/admin/moderation/reviews/{review}/queue', [ModerationController::class, 'queueReview'])
        ->name('moderation.reviews.queue');

    Route::post('/admin/moderation/reviews/{review}/hide', [ModerationController::class, 'hideReview'])
        ->name('moderation.reviews.hide');

    Route::post('/admin/moderation/reviews/{review}/restore', [ModerationController::class, 'restoreReview'])
        ->name('moderation.reviews.restore');

});

Route::middleware(['auth', 'can:admin'])->group(function () {
    Route::post('/admin/users/{user}/role', [AdminUserController::class, 'updateRole'])
        ->name('admin.users.role');

    Route::delete('/admin/comments/{comment}', [AdminContentController::class, 'deleteComment'])
        ->name('admin.comments.delete');

    Route::delete('/admin/reviews/{review}', [AdminContentController::class, 'deleteReview'])
        ->name('admin.reviews.delete');

    Route::delete('/admin/posts/{post}', [AdminContentController::class, 'deletePost'])
        ->name('admin.posts.delete');

    Route::post('/admin/promos', function (Request $request) {
        if (!safeHasTable('topbar_promos')) {
            abort(503);
        }
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_impressions' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'unlimited' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $unlimited = (bool) ($data['unlimited'] ?? false);
        TopbarPromo::create([
            'label' => $data['label'],
            'url' => $data['url'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'max_impressions' => $unlimited ? null : (isset($data['max_impressions']) ? (int) $data['max_impressions'] : null),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
        Cache::forget('topbar.promos.v1');
        return redirect()->route('admin', ['tab' => 'promos']);
    })->name('admin.promos.store');

    Route::put('/admin/promos/{promo}', function (Request $request, TopbarPromo $promo) {
        if (!safeHasTable('topbar_promos')) {
            abort(503);
        }
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_impressions' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'unlimited' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $unlimited = (bool) ($data['unlimited'] ?? false);
        $promo->update([
            'label' => $data['label'],
            'url' => $data['url'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'max_impressions' => $unlimited ? null : (isset($data['max_impressions']) ? (int) $data['max_impressions'] : null),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
        Cache::forget('topbar.promos.v1');
        return redirect()->route('admin', ['tab' => 'promos']);
    })->name('admin.promos.update');

    Route::delete('/admin/promos/{promo}', function (TopbarPromo $promo) {
        if (!safeHasTable('topbar_promos')) {
            abort(503);
        }
        $promo->delete();
        Cache::forget('topbar.promos.v1');
        return redirect()->route('admin', ['tab' => 'promos']);
    })->name('admin.promos.delete');
});

Route::prefix('legal')->group(function () {
    Route::get('/terms', fn () => redirect()->route('support.docs', ['slug' => 'terms-of-service']))->name('legal.terms');
    Route::get('/privacy', fn () => redirect()->route('support.docs', ['slug' => 'privacy-policy']))->name('legal.privacy');
    Route::get('/cookies', fn () => redirect()->route('support.docs', ['slug' => 'cookie-policy']))->name('legal.cookies');
    Route::get('/guidelines', fn () => redirect()->route('support.docs', ['slug' => 'community-guidelines']))->name('legal.guidelines');
    Route::get('/notice-and-action', fn () => redirect()->route('support.docs', ['slug' => 'notice-and-action']))->name('legal.notice');
    Route::get('/legal-notice', fn () => redirect()->route('support.docs', ['slug' => 'legal-notice']))->name('legal.legal-notice');
});

Route::fallback(function () {
    return response()
        ->view('errors.404', ['current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload()], 404);
})->name('not-found');
