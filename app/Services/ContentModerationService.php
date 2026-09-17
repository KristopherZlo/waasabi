<?php

namespace App\Services;

use App\Models\ContentReport;
use App\Models\User;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContentModerationService
{
    public function moderateUploadedImage(string $publicPath, ?User $user, string $context): array
    {
        $path = ltrim((string) (parse_url($publicPath, PHP_URL_PATH) ?: $publicPath), '/');
        if (! str_starts_with($path, 'storage/uploads/')) {
            return [
                'status' => 'error',
                'flagged' => false,
                'labels' => [],
                'reason' => 'invalid_path',
            ];
        }

        $relativePath = Str::after($path, 'storage/');
        if (
            str_contains($relativePath, '..')
            || str_contains($relativePath, '\\')
            || ! preg_match('/\Auploads\/[A-Za-z0-9_\/-]+\.(?:jpe?g|png|webp)\z/i', $relativePath)
        ) {
            return [
                'status' => 'error',
                'flagged' => false,
                'labels' => [],
                'reason' => 'invalid_path',
            ];
        }
        $result = $this->scanImageForSexualContent(Storage::disk('public')->path($relativePath));
        $needsReview = (bool) ($result['flagged'] ?? false)
            || (($result['status'] ?? '') !== 'ok'
                && config('services.rekognition.fallback_action', 'mod') === 'mod');

        if ($needsReview) {
            $details = ! empty($result['labels'])
                ? $this->formatLabels((array) $result['labels'], $context)
                : $this->formatFallback($result['reason'] ?? null, $context);

            ContentReport::firstOrCreate(
                [
                    'user_id' => $user?->id,
                    'content_type' => 'content',
                    'content_id' => $path,
                ],
                [
                    'reporter_role' => $user?->roleKey() ?? 'system',
                    'role_weight' => 1,
                    'reporter_weight' => 1,
                    'reporter_trust' => 1,
                    'weight' => 1,
                    'content_url' => asset($path),
                    'reason' => 'admin_flag',
                    'details' => $details,
                    'resolved_status' => 'pending',
                    'meta' => [
                        'context' => $context,
                        'scan' => $result,
                    ],
                ],
            );
            $result['review_requested'] = true;
        }

        return $result;
    }

    public function formatLabels(array $labels, string $context): string
    {
        $items = collect($labels)
            ->take(5)
            ->map(static function (array $label): string {
                $name = trim((string) ($label['name'] ?? $label['parent'] ?? 'label'));
                $confidence = round((float) ($label['confidence'] ?? 0), 1);

                return $name.' ('.$confidence.'%)';
            })
            ->implode(', ');

        return __('ui.admin.media_scan_flagged', ['context' => $context, 'labels' => $items]);
    }

    public function formatFallback(mixed $reason, string $context): string
    {
        $reason = trim((string) $reason) ?: __('ui.admin.media_scan_unknown_error');

        return __('ui.admin.media_scan_unavailable', ['context' => $context, 'reason' => $reason]);
    }

    public function scanImageForSexualContent(string $absolutePath): array
    {
        $result = [
            'status' => 'skipped',
            'flagged' => false,
            'labels' => [],
            'reason' => null,
        ];

        if (! config('services.rekognition.enabled')) {
            $result['reason'] = 'disabled';

            return $result;
        }

        if (! is_file($absolutePath)) {
            $result['status'] = 'error';
            $result['reason'] = 'missing_file';

            return $result;
        }

        $region = (string) config('services.rekognition.region', '');
        if ($region === '') {
            Log::warning('Rekognition region missing, skipping moderation scan.');
            $result['status'] = 'error';
            $result['reason'] = 'region_missing';

            return $result;
        }

        if (! class_exists(RekognitionClient::class)) {
            Log::warning('Rekognition SDK missing, skipping moderation scan.');
            $result['status'] = 'error';
            $result['reason'] = 'sdk_missing';

            return $result;
        }

        $imageBytes = $this->encodeToJpegBytes($absolutePath);
        if ($imageBytes === null) {
            Log::warning('Unable to encode image for moderation scan.', ['path' => $absolutePath]);
            $result['status'] = 'error';
            $result['reason'] = 'encode_failed';

            return $result;
        }

        $minConfidence = (float) config('services.rekognition.min_confidence', 75);
        $clientConfig = [
            'region' => $region,
            'version' => 'latest',
        ];
        $key = (string) config('services.rekognition.key', '');
        $secret = (string) config('services.rekognition.secret', '');
        if ($key !== '' && $secret !== '') {
            $clientConfig['credentials'] = [
                'key' => $key,
                'secret' => $secret,
            ];
        }

        try {
            $client = new RekognitionClient($clientConfig);
            $response = $client->detectModerationLabels([
                'Image' => ['Bytes' => $imageBytes],
                'MinConfidence' => $minConfidence,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Rekognition scan failed.', ['error' => $exception->getMessage()]);
            $result['status'] = 'error';
            $result['reason'] = 'rekognition_failed';

            return $result;
        }

        $labels = $response['ModerationLabels'] ?? [];
        if (! is_array($labels) || $labels === []) {
            $result['status'] = 'ok';

            return $result;
        }

        $targets = $this->targetLabels();
        if ($targets === []) {
            $result['status'] = 'ok';

            return $result;
        }

        $matches = [];
        foreach ($labels as $label) {
            $confidence = (float) ($label['Confidence'] ?? 0);
            if ($confidence < $minConfidence) {
                continue;
            }
            $name = (string) ($label['Name'] ?? '');
            $parent = (string) ($label['ParentName'] ?? '');
            if ($this->isTargetLabel($name, $targets) || $this->isTargetLabel($parent, $targets)) {
                $matches[] = [
                    'name' => $name,
                    'parent' => $parent,
                    'confidence' => round($confidence, 2),
                ];
            }
        }

        if ($matches === []) {
            $result['status'] = 'ok';

            return $result;
        }

        usort($matches, static function (array $a, array $b) {
            return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
        });

        $result['status'] = 'ok';
        $result['flagged'] = true;
        $result['labels'] = $matches;

        return $result;
    }

    private function targetLabels(): array
    {
        $raw = config('services.rekognition.sexual_labels', []);
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }
        if (! is_array($raw)) {
            return [];
        }
        $normalized = [];
        foreach ($raw as $label) {
            $label = trim((string) $label);
            if ($label !== '') {
                $normalized[] = strtolower($label);
            }
        }

        return $normalized;
    }

    private function isTargetLabel(string $label, array $targets): bool
    {
        if ($label === '') {
            return false;
        }

        return in_array(strtolower($label), $targets, true);
    }

    private function encodeToJpegBytes(string $absolutePath): ?string
    {
        $info = @getimagesize($absolutePath);
        if (! $info) {
            return null;
        }

        $type = (int) ($info[2] ?? 0);
        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : null,
            default => null,
        };

        if (! $source) {
            return null;
        }

        ob_start();
        imagejpeg($source, null, 85);
        $bytes = ob_get_clean();
        imagedestroy($source);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        return $bytes;
    }
}
