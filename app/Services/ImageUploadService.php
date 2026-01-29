<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageUploadService
{
    public function process(UploadedFile $file, array $options): array
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

        $source = $this->createImageResource($path, $type);
        if (!$source) {
            throw new RuntimeException('Unable to read image.');
        }

        $workingSource = $source;
        if ($cropAspect) {
            $workingSource = $this->cropImageResource($source, $cropX, $cropY, $cropWidth, $cropHeight, $preserveAlpha);
            if (!$workingSource) {
                imagedestroy($source);
                throw new RuntimeException('Unable to crop image.');
            }
        }

        $resized = $this->resizeImageResource($workingSource, $workingWidth, $workingHeight, $targetWidth, $targetHeight, $preserveAlpha);
        if (!$this->saveImageResource($resized, $fullPath, $format, $quality)) {
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
            $previewImage = $this->resizeImageResource($workingSource, $workingWidth, $workingHeight, $previewWidth, $previewHeight, $preserveAlpha);
            if ($this->saveImageResource($previewImage, $previewFull, $format, $quality)) {
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

    private function createImageResource(string $path, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };
    }

    private function resizeImageResource($source, int $sourceWidth, int $sourceHeight, int $targetWidth, int $targetHeight, bool $preserveAlpha)
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

    private function cropImageResource($source, int $x, int $y, int $cropWidth, int $cropHeight, bool $preserveAlpha)
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

    private function saveImageResource($image, string $path, string $format, int $quality): bool
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
