<?php

namespace App\Services;

use App\Models\ContentReport;
use App\Models\Post;
use App\Models\UploadAsset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadAssetService
{
    public function recordEditorUpload(User $user, array $result): UploadAsset
    {
        return UploadAsset::create([
            'user_id' => $user->id,
            'kind' => 'editor',
            'path' => (string) $result['path'],
            'preview_path' => $result['preview'] ?? null,
        ]);
    }

    public function syncEditorAssets(Post $post, User $user, string $content): void
    {
        $content .= "\n".$post->updates()->pluck('body')->implode("\n");
        UploadAsset::query()
            ->where('user_id', $user->id)
            ->where('kind', 'editor')
            ->where(fn ($query) => $query->whereNull('post_id')->orWhere('post_id', $post->id))
            ->get()
            ->each(function (UploadAsset $asset) use ($post, $content): void {
                if ($this->isReferenced($asset->path, $content)) {
                    $asset->update(['post_id' => $post->id]);
                } elseif ((int) $asset->post_id === (int) $post->id) {
                    $this->deleteAsset($asset);
                }
            });
    }

    public function deletePostMedia(Post $post): void
    {
        foreach ($post->attachments()->get() as $attachment) {
            $this->deletePublicPath($attachment->path);
        }
        foreach (array_filter(array_merge([$post->cover_url], (array) ($post->album_urls ?? []))) as $path) {
            $this->deletePublicPath((string) $path, 'uploads/covers/');
        }
        UploadAsset::where('post_id', $post->id)->get()->each(fn (UploadAsset $asset) => $this->deleteAsset($asset));
    }

    public function deleteUserUploads(User $user): void
    {
        UploadAsset::where('user_id', $user->id)->get()->each(fn (UploadAsset $asset) => $this->deleteAsset($asset));
    }

    public function deletePublicPath(string $path, ?string $requiredPrefix = null): void
    {
        $relative = $this->relativePath($path);
        if (
            $relative === ''
            || str_contains($relative, '..')
            || str_contains($relative, '\\')
            || ($requiredPrefix && ! str_starts_with($relative, $requiredPrefix))
        ) {
            return;
        }

        $publicPath = 'storage/'.$relative;
        ContentReport::query()
            ->where('content_type', 'content')
            ->where('resolved_status', 'pending')
            ->whereIn('content_id', array_unique([$path, $publicPath, '/'.$publicPath]))
            ->update([
                'resolved_status' => 'withdrawn',
                'resolved_at' => now(),
                'auto_action' => 'source_removed',
            ]);

        Storage::disk('public')->delete($relative);
    }

    public function deleteUploadedPath(string $path): void
    {
        $relative = $this->relativePath($path);
        if (! str_starts_with($relative, 'uploads/')) {
            return;
        }

        $variants = [$path, 'storage/'.$relative, '/storage/'.$relative];
        $assets = UploadAsset::query()->whereIn('path', array_unique($variants))->get();
        $assets->each(fn (UploadAsset $asset) => $this->deleteAsset($asset));
        $this->deletePublicPath($path, 'uploads/');
    }

    public function pruneUnattached(int $hours = 24): int
    {
        $assets = UploadAsset::whereNull('post_id')->where('created_at', '<', now()->subHours($hours))->get();
        $assets->each(fn (UploadAsset $asset) => $this->deleteAsset($asset));

        return $assets->count();
    }

    private function deleteAsset(UploadAsset $asset): void
    {
        $this->deletePublicPath($asset->path);
        if ($asset->preview_path) {
            $this->deletePublicPath($asset->preview_path);
        }
        $asset->delete();
    }

    private function isReferenced(string $path, string $content): bool
    {
        $relative = $this->relativePath($path);

        return $relative !== '' && (str_contains($content, $path) || str_contains($content, $relative));
    }

    private function relativePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: $path;

        return ltrim(Str::after(ltrim($path, '/'), 'storage/'), '/');
    }
}
