<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadImageRequest;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class UploadController extends Controller
{
    public function storeImage(UploadImageRequest $request, ImageUploadService $uploadService): JsonResponse
    {
        $file = $request->file('image');
        if (!$file instanceof UploadedFile) {
            return response()->json(['message' => 'Invalid upload'], 422);
        }

        try {
            $result = $uploadService->process($file, [
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
    }
}
