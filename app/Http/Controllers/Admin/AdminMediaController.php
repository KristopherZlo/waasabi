<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\Post;
use App\Models\User;
use App\Services\UploadAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminMediaController extends Controller
{
    public function resolve(Request $request, ContentReport $report, UploadAssetService $assets): RedirectResponse
    {
        abort_unless($report->content_type === 'content' && $report->resolved_status === 'pending', 404);
        $data = $request->validate([
            'action' => ['required', Rule::in(['dismiss', 'remove'])],
        ]);
        $remove = $data['action'] === 'remove';
        $path = ltrim((string) (parse_url((string) $report->content_id, PHP_URL_PATH) ?: $report->content_id), '/');
        abort_unless(str_starts_with($path, 'storage/uploads/'), 422);

        DB::transaction(function () use ($report, $path, $remove): void {
            if ($remove) {
                $this->removeReferences($path);
            }

            ContentReport::query()
                ->where('content_type', 'content')
                ->where('content_id', $report->content_id)
                ->where('resolved_status', 'pending')
                ->update([
                    'resolved_status' => $remove ? 'confirmed' : 'rejected',
                    'resolved_at' => now(),
                    'auto_action' => $remove ? 'remove_media' : 'dismiss_media',
                ]);
        });

        if ($remove) {
            $assets->deleteUploadedPath($path);
        }

        logAuditEvent($request, 'admin.media.'.($remove ? 'removed' : 'dismissed'), $request->user(), [
            'path' => $path,
            'report_id' => $report->id,
        ], 'content_report', (string) $report->id);

        return redirect()->route('admin', ['tab' => 'media'])
            ->with('toast', __($remove ? 'ui.admin.media_removed' : 'ui.admin.media_dismissed'));
    }

    private function removeReferences(string $path): void
    {
        $variants = array_values(array_unique([$path, '/'.$path, asset($path)]));
        User::query()->whereIn('avatar', $variants)->update(['avatar' => null]);
        User::query()->whereIn('banner_url', $variants)->update(['banner_url' => null]);
        Post::query()->whereIn('cover_url', $variants)->update(['cover_url' => null]);

        Post::query()
            ->whereNotNull('album_urls')
            ->get()
            ->each(function (Post $post) use ($path): void {
                $album = collect((array) $post->album_urls)
                    ->reject(fn ($item) => $this->samePath((string) $item, $path))
                    ->values()
                    ->all();
                if ($album !== (array) $post->album_urls) {
                    $post->update(['album_urls' => $album]);
                }
            });

        Post::query()
            ->where(function ($query) use ($variants): void {
                foreach ($variants as $variant) {
                    $query->orWhere('body_html', 'like', '%'.$variant.'%')
                        ->orWhere('body_markdown', 'like', '%'.$variant.'%');
                }
            })
            ->get()
            ->each(function (Post $post) use ($variants): void {
                $html = (string) $post->body_html;
                $markdown = (string) $post->body_markdown;
                foreach ($variants as $variant) {
                    $quoted = preg_quote($variant, '~');
                    $html = (string) preg_replace('~<img\b[^>]*'.$quoted.'[^>]*>~i', '', $html);
                    $markdown = (string) preg_replace('~!\[[^]]*]\([^)]*'.$quoted.'[^)]*\)~i', '', $markdown);
                }
                $post->update(['body_html' => $html, 'body_markdown' => $markdown]);
            });
    }

    private function samePath(string $left, string $right): bool
    {
        $normalize = static fn (string $value): string => ltrim(Str::after(
            ltrim((string) (parse_url($value, PHP_URL_PATH) ?: $value), '/'),
            'storage/',
        ), '/');

        return $normalize($left) === $normalize($right);
    }
}
