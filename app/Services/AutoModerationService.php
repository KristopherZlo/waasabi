<?php

namespace App\Services;

use App\Models\CollaborationComment;
use App\Models\CollaborationRequest;
use App\Models\ContentReport;
use App\Models\ContentReportScore;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\User;
use App\Models\UserReportProfile;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutoModerationService
{
    public function handleReport(Request $request, array $data): array
    {
        $reporter = $request->user();
        $contentType = (string) ($data['content_type'] ?? '');
        $providedContentId = $data['content_id'] ?? null;

        $resolved = $this->resolveContent($contentType, $providedContentId);
        $canonicalId = $resolved['canonical_id'];
        $identifiers = $resolved['identifiers'];
        $model = $resolved['model'];
        $slug = $resolved['slug'];

        if (! $model) {
            return ['ok' => false, 'missing' => true];
        }

        if ($reporter && $canonicalId !== '' && $this->hasExistingReport($reporter, $contentType, $identifiers)) {
            return ['ok' => true, 'duplicate' => true];
        }

        $weights = $this->computeReporterWeight($reporter);

        $reportMeta = [
            'canonical_id' => $canonicalId !== '' ? $canonicalId : ($providedContentId ?? ''),
            'identifiers' => $identifiers,
            'slug' => $slug,
        ];

        try {
            $report = ContentReport::create([
                'user_id' => $reporter?->id,
                'reporter_role' => $weights['role_key'],
                'role_weight' => $weights['role_weight'],
                'reporter_weight' => $weights['reporter_weight'],
                'reporter_trust' => $weights['trust_score'],
                'weight' => $weights['report_weight'],
                'content_type' => $contentType,
                'content_id' => $canonicalId !== '' ? $canonicalId : ($providedContentId ?? null),
                'content_url' => $this->resolveContentUrl($contentType, $model, $slug),
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
                'resolved_status' => 'pending',
                'meta' => $reportMeta,
            ]);
        } catch (UniqueConstraintViolationException) {
            return ['ok' => true, 'duplicate' => true];
        }

        if ($reporter && $weights['profile'] instanceof UserReportProfile) {
            $weights['profile']->increment('reports_submitted');
        }

        $siteScale = $this->calculateSiteScale();
        $report->meta = array_merge((array) $report->meta, ['site_scale' => $siteScale]);
        $report->save();
        $threshold = $this->computeAutoHideThreshold($contentType, $siteScale);
        $score = $this->recomputeScore($contentType, $canonicalId, $identifiers, $slug, $threshold, $siteScale);
        $autoHidden = $this->maybeAutoHide($request, $model, $contentType, $canonicalId, $identifiers, $slug, $score, $threshold);

        return [
            'ok' => true,
            'report_id' => $report->id,
            'report_weight' => $report->weight,
            'weight_total' => $score?->weight_total,
            'weight_threshold' => $threshold,
            'auto_hidden' => $autoHidden,
        ];
    }

    public function resolveReportsForModel(
        mixed $model,
        string $resolution,
        string $action,
    ): void {
        $contentType = $this->modelContentType($model);
        if ($contentType === null) {
            return;
        }

        $canonicalId = $this->canonicalIdForModel($model);
        if ($canonicalId === '') {
            return;
        }

        $identifiers = $this->identifiersForModel($model);
        $slug = $model instanceof Post ? (string) ($model->slug ?? '') : null;

        $siteScale = $this->calculateSiteScale();
        $threshold = $this->computeAutoHideThreshold($contentType, $siteScale);
        $this->resolveReportsForContent($contentType, $canonicalId, $identifiers, $resolution, $action);
        $this->recomputeScore($contentType, $canonicalId, $identifiers, $slug, $threshold, $siteScale);
    }

    public function withdrawReportsForUser(User $user): void
    {
        $posts = $user->posts()->get();
        $postIds = $posts->pluck('id')->all();
        $posts->each(fn (Post $post) => $this->resolveReportsForModel($post, 'withdrawn', 'source_removed'));

        PostComment::query()
            ->where('user_id', $user->id)
            ->when($postIds !== [], fn ($query) => $query->orWhereIn('post_id', $postIds))
            ->get()
            ->each(fn (PostComment $comment) => $this->resolveReportsForModel($comment, 'withdrawn', 'source_removed'));
        PostReview::query()
            ->where('user_id', $user->id)
            ->when($postIds !== [], fn ($query) => $query->orWhereIn('post_id', $postIds))
            ->get()
            ->each(fn (PostReview $review) => $this->resolveReportsForModel($review, 'withdrawn', 'source_removed'));

        $this->resolveReportsForContent(
            'profile',
            (string) $user->id,
            array_values(array_filter([(string) $user->id, (string) $user->slug])),
            'withdrawn',
            'source_removed',
        );
    }

    public function withdrawReportsForPostInteractions(Post $post): void
    {
        $post->comments()->get()
            ->each(fn (PostComment $comment) => $this->resolveReportsForModel($comment, 'withdrawn', 'source_removed'));
        $post->reviews()->get()
            ->each(fn (PostReview $review) => $this->resolveReportsForModel($review, 'withdrawn', 'source_removed'));
    }

    private function resolveReportsForContent(
        string $contentType,
        string $canonicalId,
        array $identifiers,
        string $resolution,
        string $action,
    ): void {
        if (empty($identifiers)) {
            return;
        }

        $query = ContentReport::query()
            ->where('content_type', $contentType)
            ->whereIn('content_id', $identifiers);

        $query->where(function ($sub): void {
            $sub->whereNull('resolved_status')->orWhere('resolved_status', 'pending');
        });

        $reports = $query->get(['id', 'user_id']);
        if ($reports->isEmpty()) {
            return;
        }

        $ids = $reports->pluck('id')->all();
        $countsByUser = [];
        foreach ($reports as $report) {
            if (! $report->user_id) {
                continue;
            }
            $countsByUser[$report->user_id] = ($countsByUser[$report->user_id] ?? 0) + 1;
        }

        ContentReport::query()
            ->whereIn('id', $ids)
            ->update([
                'resolved_status' => $resolution,
                'resolved_at' => now(),
                'auto_action' => $action,
            ]);

        $this->updateProfilesForResolution($countsByUser, $resolution);
    }

    private function maybeAutoHide(
        Request $request,
        mixed $model,
        string $contentType,
        string $canonicalId,
        array $identifiers,
        ?string $slug,
        ?ContentReportScore $score,
        float $threshold
    ): bool {
        if (! $model || ! $score) {
            return false;
        }

        if (! in_array($contentType, ['post', 'question'], true)) {
            return false;
        }

        $minimumReports = (int) config('moderation.reports.auto_hide.minimum_reports', 3);
        if (($score->reports_count ?? 0) < $minimumReports) {
            return false;
        }

        $weightTotal = (float) ($score->weight_total ?? 0);
        if ($weightTotal < $threshold) {
            return false;
        }

        $isHidden = (bool) ($model->is_hidden ?? false);
        $status = (string) ($model->moderation_status ?? 'approved');
        if ($isHidden || $status !== 'approved') {
            return false;
        }

        $model->is_hidden = true;
        $model->moderation_status = 'hidden';
        $model->hidden_at = now();
        $model->hidden_by = null;
        $model->save();

        $siteScale = (float) ($score->site_scale ?? 1);
        $this->resolveReportsForContent($contentType, $canonicalId, $identifiers, 'auto_hidden', 'auto_hide');
        $score->auto_hidden_at = now();
        $score->weight_threshold = $threshold;
        $score->site_scale = $siteScale;
        $score->last_recomputed_at = now();
        $score->meta = array_merge((array) $score->meta, [
            'auto_hidden' => true,
            'auto_hidden_reason' => 'weight_threshold_reached',
        ]);
        $score->save();

        $contentUrl = $this->resolveContentUrl($contentType, $model, $slug);
        $this->logSystemAutoHide(
            $request,
            $contentType,
            $canonicalId,
            $contentUrl,
            [
                'weight_total' => $weightTotal,
                'weight_threshold' => $threshold,
                'site_scale' => $siteScale,
                'reports_count' => (int) ($score->reports_count ?? 0),
                'reporters_count' => (int) ($score->reporters_count ?? 0),
                'slug' => $slug,
                'title' => $model instanceof Post ? $model->title : null,
                'author_id' => $model->user_id ?? null,
                'author_name' => $model->user?->name ?? null,
            ],
        );

        return true;
    }

    private function resolveContent(string $contentType, mixed $contentId): array
    {
        $canonicalId = $contentId !== null ? (string) $contentId : '';
        $identifiers = $canonicalId !== '' ? [$canonicalId] : [];
        $slug = null;
        $model = null;

        if ($contentType === 'post' || $contentType === 'question') {
            $type = $contentType === 'question' ? 'question' : 'post';
            $query = Post::query()->where('type', $type);
            if (is_string($contentId) && ctype_digit($contentId)) {
                $query->where('id', (int) $contentId);
            } elseif (is_numeric($contentId)) {
                $query->where('id', (int) $contentId);
            } elseif (is_string($contentId) && trim($contentId) !== '') {
                $query->where('slug', $contentId);
            }
            $model = $query->with('user')->first();
            if ($model) {
                $canonicalId = (string) $model->id;
                $slug = (string) ($model->slug ?? '');
                $identifiers = array_values(array_unique(array_filter([$canonicalId, $slug])));
            }
        } elseif ($contentType === 'comment') {
            if (is_string($contentId) && ctype_digit($contentId)) {
                $model = PostComment::with('user')->find((int) $contentId);
            } elseif (is_numeric($contentId)) {
                $model = PostComment::with('user')->find((int) $contentId);
            }
            if ($model) {
                $canonicalId = (string) $model->id;
                $identifiers = [$canonicalId];
            }
        } elseif ($contentType === 'review') {
            if (is_string($contentId) && ctype_digit($contentId)) {
                $model = PostReview::with('user')->find((int) $contentId);
            } elseif (is_numeric($contentId)) {
                $model = PostReview::with('user')->find((int) $contentId);
            }
            if ($model) {
                $canonicalId = (string) $model->id;
                $identifiers = [$canonicalId];
            }
        } elseif ($contentType === 'profile') {
            if (is_string($contentId) && ctype_digit($contentId)) {
                $model = User::find((int) $contentId);
            } elseif (is_numeric($contentId)) {
                $model = User::find((int) $contentId);
            } elseif (is_string($contentId) && trim($contentId) !== '') {
                $model = User::where('slug', $contentId)->first();
            }
            if ($model) {
                $canonicalId = (string) $model->id;
                $identifiers = array_values(array_filter([$canonicalId, (string) $model->slug]));
            }
        } elseif ($contentType === 'collaboration') {
            if ((is_string($contentId) && ctype_digit($contentId)) || is_numeric($contentId)) {
                $model = CollaborationRequest::with('user')->find((int) $contentId);
            }
            if ($model) {
                $canonicalId = (string) $model->id;
                $identifiers = [$canonicalId];
            }
        } elseif ($contentType === 'collaboration_comment') {
            if ((is_string($contentId) && ctype_digit($contentId)) || is_numeric($contentId)) {
                $model = CollaborationComment::with(['user', 'collaborationRequest'])->find((int) $contentId);
            }
            if ($model) {
                $canonicalId = (string) $model->id;
                $identifiers = [$canonicalId];
            }
        }

        return [
            'canonical_id' => $canonicalId,
            'identifiers' => $identifiers,
            'slug' => $slug,
            'model' => $model,
        ];
    }

    private function hasExistingReport(User $reporter, string $contentType, array $identifiers): bool
    {
        if (empty($identifiers)) {
            return false;
        }

        return ContentReport::query()
            ->where('user_id', $reporter->id)
            ->where('content_type', $contentType)
            ->whereIn('content_id', $identifiers)
            ->exists();
    }

    private function computeReporterWeight(?User $reporter): array
    {
        $roleKey = $reporter?->roleKey() ?? 'user';
        $roleWeights = (array) config('moderation.reports.role_weights', []);
        $roleWeight = (float) ($roleWeights[$roleKey] ?? 1.0);

        if (! $reporter) {
            return [
                'role_key' => $roleKey,
                'role_weight' => $roleWeight,
                'activity_points' => 0.0,
                'trust_score' => 1.0,
                'reporter_weight' => $roleWeight,
                'report_weight' => max(1.0, $roleWeight),
                'profile' => null,
            ];
        }

        $profile = $this->getOrCreateProfile($reporter);
        $activityPoints = $this->computeActivityPoints($reporter);
        $activityDivisor = (float) config('moderation.reports.activity.divisor', 90.0);
        $activityMultiplier = 1.0 + ($activityDivisor > 0 ? $activityPoints / $activityDivisor : 0.0);

        $accuracyMultiplier = $this->computeAccuracyMultiplier($profile);
        $minTrust = (float) config('moderation.reports.accuracy.min_trust', 1.0);
        $maxTrust = (float) config('moderation.reports.accuracy.max_trust', 4.0);
        $trustScore = $this->clamp($activityMultiplier * $accuracyMultiplier, $minTrust, $maxTrust);

        $minWeight = (float) config('moderation.reports.min_weight', 1.0);
        $maxWeight = (float) config('moderation.reports.max_weight', 12.0);
        $reporterWeight = $this->clamp($roleWeight * $trustScore, $minWeight, $maxWeight);

        $profile->activity_points = $activityPoints;
        $profile->trust_score = $trustScore;
        $profile->weight = $reporterWeight;
        $profile->last_computed_at = now();
        $profile->meta = array_merge((array) $profile->meta, [
            'role_weight' => $roleWeight,
            'activity_multiplier' => $activityMultiplier,
            'accuracy_multiplier' => $accuracyMultiplier,
        ]);
        $profile->save();

        return [
            'role_key' => $roleKey,
            'role_weight' => $roleWeight,
            'activity_points' => $activityPoints,
            'trust_score' => $trustScore,
            'reporter_weight' => $reporterWeight,
            'report_weight' => $reporterWeight,
            'profile' => $profile,
        ];
    }

    private function computeActivityPoints(User $reporter): float
    {
        $config = (array) config('moderation.reports.activity', []);
        $postPoints = (float) ($config['post_points'] ?? 8.0);
        $commentPoints = (float) ($config['comment_points'] ?? 2.0);
        $reviewPoints = (float) ($config['review_points'] ?? 4.0);
        $followPoints = (float) ($config['follow_points'] ?? 1.0);
        $upvotePoints = (float) ($config['upvote_points'] ?? 0.25);
        $savePoints = (float) ($config['save_points'] ?? 0.25);
        $agePointsPerDay = (float) ($config['age_points_per_day'] ?? 0.35);
        $ageDaysCap = (int) ($config['age_days_cap'] ?? 365);
        $cap = (float) ($config['cap'] ?? 180.0);

        $userId = $reporter->id;
        $points = 0.0;

        $postsCount = (int) DB::table('posts')
            ->where('user_id', $userId)
            ->whereIn('type', ['post', 'question'])
            ->count();
        $points += $postsCount * $postPoints;

        $commentsCount = (int) DB::table('post_comments')->where('user_id', $userId)->count();
        $points += $commentsCount * $commentPoints;

        $reviewsCount = (int) DB::table('post_reviews')->where('user_id', $userId)->count();
        $points += $reviewsCount * $reviewPoints;

        $followsCount = (int) DB::table('user_follows')
            ->where('follower_id', $userId)
            ->count();
        $points += $followsCount * $followPoints;

        $upvotesCount = (int) DB::table('post_upvotes')
            ->where('user_id', $userId)
            ->count();
        $points += $upvotesCount * $upvotePoints;

        $savesCount = (int) DB::table('post_saves')
            ->where('user_id', $userId)
            ->count();
        $points += $savesCount * $savePoints;

        $ageDays = 0;
        if ($reporter->created_at) {
            $ageDays = max(0, now()->diffInDays($reporter->created_at));
            $ageDays = min($ageDays, $ageDaysCap);
        }
        $points += $ageDays * $agePointsPerDay;

        return min($points, $cap);
    }

    private function computeAccuracyMultiplier(UserReportProfile $profile): float
    {
        $submitted = (int) ($profile->reports_submitted ?? 0);
        if ($submitted <= 0) {
            return 1.0;
        }

        $accuracyConfig = (array) config('moderation.reports.accuracy', []);
        $boostMax = (float) ($accuracyConfig['boost_max'] ?? 0.6);
        $penaltyMax = (float) ($accuracyConfig['penalty_max'] ?? 0.6);
        $minMultiplier = (float) ($accuracyConfig['min_multiplier'] ?? 0.5);

        $confirmed = (int) ($profile->reports_confirmed ?? 0) + (int) ($profile->reports_auto_hidden ?? 0);
        $rejected = (int) ($profile->reports_rejected ?? 0);
        $confirmedRatio = $submitted > 0 ? $confirmed / $submitted : 0.0;
        $rejectedRatio = $submitted > 0 ? $rejected / $submitted : 0.0;

        $boost = $confirmedRatio * $boostMax;
        $penalty = $rejectedRatio * $penaltyMax;

        $multiplier = 1.0 + $boost - $penalty;
        $maxMultiplier = 1.0 + $boostMax;

        return $this->clamp($multiplier, $minMultiplier, $maxMultiplier);
    }

    private function calculateSiteScale(): float
    {
        $config = (array) config('moderation.reports.site_scale', []);
        $windowDays = max(1, (int) ($config['window_days'] ?? 7));
        $baseReportsPerDay = max(1.0, (float) ($config['base_reports_per_day'] ?? 12.0));
        $sensitivity = (float) ($config['sensitivity'] ?? 0.35);
        $minScale = (float) ($config['min_scale'] ?? 0.75);
        $maxScale = (float) ($config['max_scale'] ?? 1.6);

        $recentCount = (int) ContentReport::query()
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->count();
        $reportsPerDay = $recentCount / $windowDays;
        $deltaRatio = ($reportsPerDay - $baseReportsPerDay) / $baseReportsPerDay;
        $scale = 1.0 + ($deltaRatio * $sensitivity);

        return $this->clamp($scale, $minScale, $maxScale);
    }

    private function computeAutoHideThreshold(string $contentType, float $siteScale): float
    {
        $baseThreshold = (float) config('moderation.reports.auto_hide.base_threshold', 16.0);
        $multiplier = 1.0;
        if ($contentType === 'question') {
            $multiplier = (float) config('moderation.reports.auto_hide.question_multiplier', 1.1);
        }

        return max(1.0, $baseThreshold * $siteScale * $multiplier);
    }

    private function recomputeScore(
        string $contentType,
        string $canonicalId,
        array $identifiers,
        ?string $slug,
        float $threshold,
        float $siteScale
    ): ?ContentReportScore {
        if ($canonicalId === '') {
            return null;
        }

        $aggregate = $this->aggregateReports($contentType, $identifiers);
        $weightTotal = (float) ($aggregate['weight_total'] ?? 0.0);

        $score = ContentReportScore::query()->updateOrCreate(
            [
                'content_type' => $contentType,
                'content_id' => $canonicalId,
            ],
            [
                'reports_count' => (int) ($aggregate['reports_count'] ?? 0),
                'reporters_count' => (int) ($aggregate['reporters_count'] ?? 0),
                'weight_total' => $weightTotal,
                'weight_threshold' => $threshold,
                'site_scale' => $siteScale,
                'last_report_at' => $aggregate['last_report_at'] ?? null,
                'last_recomputed_at' => now(),
                'meta' => [
                    'identifiers' => $identifiers,
                    'slug' => $slug,
                ],
            ],
        );

        return $score;
    }

    private function aggregateReports(string $contentType, array $identifiers): array
    {
        if (empty($identifiers)) {
            return [
                'reports_count' => 0,
                'reporters_count' => 0,
                'weight_total' => 0.0,
                'last_report_at' => null,
            ];
        }

        $row = DB::table('content_reports')
            ->selectRaw(
                'count(*) as reports_count, count(distinct coalesce(user_id, id)) as reporters_count, max(created_at) as last_report_at, '
                .'coalesce(sum(weight), 0) as weight_total'
            )
            ->where('content_type', $contentType)
            ->whereIn('content_id', $identifiers)
            ->first();

        return [
            'reports_count' => (int) ($row?->reports_count ?? 0),
            'reporters_count' => (int) ($row?->reporters_count ?? 0),
            'weight_total' => (float) ($row?->weight_total ?? 0.0),
            'last_report_at' => $row?->last_report_at ?? null,
        ];
    }

    private function updateProfilesForResolution(array $countsByUser, string $resolution): void
    {
        if (empty($countsByUser) || ! in_array($resolution, ['rejected', 'auto_hidden', 'confirmed'], true)) {
            return;
        }

        foreach ($countsByUser as $userId => $count) {
            if (! is_int($userId) || $userId <= 0 || $count <= 0) {
                continue;
            }
            $user = User::find($userId);
            if (! $user) {
                continue;
            }
            $profile = $this->getOrCreateProfile($user);
            if ($resolution === 'rejected') {
                $profile->reports_rejected += $count;
            } elseif ($resolution === 'auto_hidden') {
                $profile->reports_auto_hidden += $count;
            } elseif ($resolution === 'confirmed') {
                $profile->reports_confirmed += $count;
            }

            // Recompute trust after outcomes change to keep weights adaptive.
            $activityPoints = $this->computeActivityPoints($user);
            $activityDivisor = (float) config('moderation.reports.activity.divisor', 90.0);
            $activityMultiplier = 1.0 + ($activityDivisor > 0 ? $activityPoints / $activityDivisor : 0.0);
            $accuracyMultiplier = $this->computeAccuracyMultiplier($profile);
            $minTrust = (float) config('moderation.reports.accuracy.min_trust', 1.0);
            $maxTrust = (float) config('moderation.reports.accuracy.max_trust', 4.0);
            $trustScore = $this->clamp($activityMultiplier * $accuracyMultiplier, $minTrust, $maxTrust);

            $roleKey = $user->roleKey();
            $roleWeights = (array) config('moderation.reports.role_weights', []);
            $roleWeight = (float) ($roleWeights[$roleKey] ?? 1.0);
            $minWeight = (float) config('moderation.reports.min_weight', 1.0);
            $maxWeight = (float) config('moderation.reports.max_weight', 12.0);
            $weight = $this->clamp($roleWeight * $trustScore, $minWeight, $maxWeight);

            $profile->activity_points = $activityPoints;
            $profile->trust_score = $trustScore;
            $profile->weight = $weight;
            $profile->last_computed_at = now();
            $profile->meta = array_merge((array) $profile->meta, [
                'role_weight' => $roleWeight,
                'activity_multiplier' => $activityMultiplier,
                'accuracy_multiplier' => $accuracyMultiplier,
            ]);
            $profile->save();
        }
    }

    private function getOrCreateProfile(User $user): UserReportProfile
    {
        return UserReportProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'reports_submitted' => 0,
                'reports_confirmed' => 0,
                'reports_rejected' => 0,
                'reports_auto_hidden' => 0,
                'activity_points' => 0,
                'trust_score' => 1,
                'weight' => 1,
            ],
        );
    }

    private function modelContentType(mixed $model): ?string
    {
        if ($model instanceof Post) {
            return $model->type === 'question' ? 'question' : 'post';
        }
        if ($model instanceof PostComment) {
            return 'comment';
        }
        if ($model instanceof PostReview) {
            return 'review';
        }
        if ($model instanceof CollaborationRequest) {
            return 'collaboration';
        }
        if ($model instanceof CollaborationComment) {
            return 'collaboration_comment';
        }

        return null;
    }

    private function canonicalIdForModel(mixed $model): string
    {
        if (isset($model->id)) {
            return (string) $model->id;
        }

        return '';
    }

    private function identifiersForModel(mixed $model): array
    {
        $ids = [];
        if (isset($model->id)) {
            $ids[] = (string) $model->id;
        }
        if ($model instanceof Post && ! empty($model->slug)) {
            $ids[] = (string) $model->slug;
        }

        return array_values(array_unique(array_filter($ids, static fn ($id) => $id !== '')));
    }

    private function resolveContentUrl(string $contentType, mixed $model, ?string $slug): ?string
    {
        if ($contentType === 'question' && $slug) {
            return route('questions.show', $slug);
        }
        if ($contentType === 'post' && $slug) {
            return route('project', $slug);
        }
        if ($contentType === 'comment' && $model instanceof PostComment && $model->post_slug) {
            $base = $this->resolvePostUrlFromSlug($model->post_slug);

            return $base ? ($base.'#comment-'.$model->id) : null;
        }
        if ($contentType === 'review' && $model instanceof PostReview && $model->post_slug) {
            $base = $this->resolvePostUrlFromSlug($model->post_slug);

            return $base ? ($base.'#review-'.$model->id) : null;
        }
        if ($contentType === 'profile' && $model instanceof User && $model->slug) {
            return route('profile.show', $model->slug);
        }
        if ($contentType === 'collaboration' && $model instanceof CollaborationRequest) {
            return route('collaboration.show', $model);
        }
        if ($contentType === 'collaboration_comment' && $model instanceof CollaborationComment && $model->collaborationRequest) {
            return route('collaboration.show', $model->collaborationRequest).'#comment-'.$model->id;
        }

        return null;
    }

    private function logSystemAutoHide(
        Request $request,
        string $contentType,
        string $contentId,
        ?string $contentUrl,
        array $meta
    ): void {
        ModerationLog::create([
            'moderator_id' => null,
            'moderator_name' => 'system:auto-moderation',
            'moderator_role' => 'system',
            'action' => 'auto_hide',
            'content_type' => $contentType,
            'content_id' => $contentId,
            'content_url' => $contentUrl,
            'notes' => __('ui.moderation.auto_hidden_note'),
            'ip_address' => $request->ip(),
            'location' => $this->resolveLocation($request),
            'user_agent' => $request->userAgent(),
            'meta' => $meta,
        ]);
    }

    private function resolvePostUrlFromSlug(?string $slug): ?string
    {
        $slug = is_string($slug) ? trim($slug) : '';
        if ($slug === '') {
            return null;
        }

        $type = DB::table('posts')->where('slug', $slug)->value('type');
        if ($type === 'question') {
            return route('questions.show', $slug);
        }

        return route('project', $slug);
    }

    private function resolveLocation(Request $request): ?string
    {
        $country = $request->header('X-Geo-Country') ?? $request->header('X-Country');
        $country = is_string($country) ? trim($country) : null;
        if ($country === '') {
            $country = null;
        }

        $region = $request->header('X-Geo-Region') ?? $request->header('X-Region');
        $region = is_string($region) ? trim($region) : null;
        if ($region === '') {
            $region = null;
        }

        if ($country && $region) {
            return $country.'-'.$region;
        }

        return $country ?: $region;
    }

    private function clamp(float $value, float $min, float $max): float
    {
        if ($min > $max) {
            return $value;
        }

        return max($min, min($max, $value));
    }
}
