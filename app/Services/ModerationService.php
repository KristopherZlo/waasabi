<?php

namespace App\Services;

use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ModerationService
{
    public function logAction(Request $request, User $moderator, string $action, string $contentType, ?string $contentId, ?string $contentUrl, ?string $notes = null, array $meta = []): void
    {
        if (!$this->safeHasTable('moderation_logs')) {
            return;
        }

        if (!$moderator->hasRole('moderator')) {
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
            'location' => $this->resolveLocation($request),
            'user_agent' => $request->userAgent(),
            'meta' => $meta,
        ]);
    }

    public function logSystemAction(Request $request, string $action, string $contentType, ?string $contentId, ?string $contentUrl, ?string $notes = null, array $meta = []): void
    {
        if (!$this->safeHasTable('moderation_logs')) {
            return;
        }

        ModerationLog::create([
            'moderator_id' => null,
            'moderator_name' => 'system',
            'moderator_role' => 'system',
            'action' => $action,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'content_url' => $contentUrl,
            'notes' => $notes,
            'ip_address' => $request->ip(),
            'location' => $this->resolveLocation($request),
            'user_agent' => $request->userAgent(),
            'meta' => $meta,
        ]);
    }

    public function resolvePostUrl(string $slug): string
    {
        if ($this->safeHasTable('posts')) {
            $post = Post::where('slug', $slug)->first();
            if ($post?->type === 'question') {
                return route('questions.show', $slug);
            }
        }

        return route('project', $slug);
    }

    public function shouldBlock(User $actor, ?User $owner): bool
    {
        return $owner !== null && $actor->roleKey() === 'moderator' && $owner->isAdmin();
    }

    public function setState($model, ?User $actor, string $status): void
    {
        $isHidden = $status !== 'approved';
        $model->is_hidden = $isHidden;
        $model->moderation_status = $status;
        $model->hidden_at = $isHidden ? now() : null;
        $model->hidden_by = $isHidden ? ($actor?->id) : null;
        $model->save();
    }

    private function resolveLocation(Request $request): ?string
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

    private function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
