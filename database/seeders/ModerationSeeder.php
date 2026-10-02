<?php

namespace Database\Seeders;

use App\Models\CollaborationComment;
use App\Models\CollaborationRequest;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\User;
use App\Services\AutoModerationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;

class ModerationSeeder extends Seeder
{
    public function run(AutoModerationService $moderation): void
    {
        $users = User::query()->whereIn('email', [
            'dasha@thehub.test',
            'ilya@thehub.test',
            'sveta@thehub.test',
            'timur@thehub.test',
            'mila@thehub.test',
            'katya@thehub.test',
            'artem@thehub.test',
            'lena@thehub.test',
            'elina@thehub.test',
            'noora@thehub.test',
        ])->get()->keyBy('email');

        $posts = Post::query()->whereIn('slug', [
            'fast-breakdown',
            'weekend-gesture-board',
            'field-notes',
            'read-time-metrics',
        ])->get()->keyBy('slug');

        $reporters = [
            'dasha@thehub.test',
            'ilya@thehub.test',
            'sveta@thehub.test',
            'timur@thehub.test',
            'mila@thehub.test',
            'katya@thehub.test',
            'artem@thehub.test',
            'lena@thehub.test',
            'elina@thehub.test',
            'noora@thehub.test',
        ];

        // Enough distinct reporters to exercise the weighted auto-hide path.
        foreach ($reporters as $index => $email) {
            $this->report(
                $moderation,
                $users->get($email),
                'post',
                $posts->get('fast-breakdown'),
                $index % 3 === 0 ? 'spam' : 'abuse',
                $index % 3 === 0
                    ? 'The same promotional link appears repeatedly in the post.'
                    : 'The wording targets another member instead of discussing the work.',
            );
        }
        $autoHideTarget = $posts->get('fast-breakdown');
        $autoHideTarget?->refresh();
        if ($autoHideTarget?->is_hidden) {
            $moderation->resolveReportsForModel($autoHideTarget, 'auto_hidden', 'auto_hide');
        }

        $samples = [
            ['katya@thehub.test', 'post', $posts->get('weekend-gesture-board'), 'offtopic', 'Most of the text is unrelated to the prototype shown here.'],
            ['elina@thehub.test', 'post', $posts->get('weekend-gesture-board'), 'other', 'Several claims need a moderator to check the surrounding context.'],
            ['artem@thehub.test', 'post', $posts->get('weekend-gesture-board'), 'spam', 'The closing section reads like an unrelated advertisement.'],
            ['mila@thehub.test', 'question', $posts->get('read-time-metrics'), 'offtopic', 'This question appears in the wrong section of the community.'],
            ['noora@thehub.test', 'question', $posts->get('read-time-metrics'), 'other', 'The linked source and the stated numbers do not match.'],
        ];

        $comment = PostComment::query()->where('body', 'Super relatable. Which regulator did you settle on?')->first();
        $review = PostReview::query()->where('post_slug', 'power-hub-night')->oldest('id')->first();
        $profile = User::query()->where('email', 'nikita@thehub.test')->first();
        $collaboration = CollaborationRequest::query()
            ->whereHas('post', fn ($query) => $query->where('slug', 'collab-quiet-dashboard'))
            ->first();
        $collaborationComment = CollaborationComment::query()
            ->whereNull('collaboration_application_id')
            ->oldest('id')
            ->first();

        array_push(
            $samples,
            ['sveta@thehub.test', 'comment', $comment, 'abuse', 'The reply is needlessly hostile toward the author.'],
            ['elina@thehub.test', 'comment', $comment, 'other', 'This may contain personal information that should be reviewed.'],
            ['dasha@thehub.test', 'review', $review, 'offtopic', 'The review does not address the project it is attached to.'],
            ['katya@thehub.test', 'review', $review, 'abuse', 'The feedback includes a personal attack.'],
            ['mila@thehub.test', 'profile', $profile, 'spam', 'The profile description contains repeated promotional links.'],
            ['timur@thehub.test', 'collaboration', $collaboration, 'other', 'The requested work and the public project description conflict.'],
            ['elina@thehub.test', 'collaboration', $collaboration, 'spam', 'The collaboration request redirects people to an unrelated service.'],
            ['katya@thehub.test', 'collaboration_comment', $collaborationComment, 'abuse', 'This message attacks an applicant instead of discussing the request.'],
        );

        foreach ($samples as [$email, $type, $target, $reason, $details]) {
            $this->report($moderation, $users->get($email), $type, $target, $reason, $details);
        }

        // A resolved group keeps the moderation history view useful as well.
        $resolvedTarget = $posts->get('field-notes');
        $this->report($moderation, $users->get('timur@thehub.test'), 'post', $resolvedTarget, 'other', 'The attribution looked unclear and needed a manual review.');
        $this->report($moderation, $users->get('elina@thehub.test'), 'post', $resolvedTarget, 'other', 'I could not verify one of the quoted sources.');
        if ($resolvedTarget) {
            $moderation->resolveReportsForModel($resolvedTarget, 'rejected', 'seed_review');
        }
    }

    private function report(
        AutoModerationService $moderation,
        ?User $reporter,
        string $contentType,
        ?Model $target,
        string $reason,
        string $details,
    ): array {
        if (! $reporter || ! $target) {
            return [];
        }

        $ownerId = $target instanceof User ? $target->id : $target->getAttribute('user_id');
        if ((int) $ownerId === (int) $reporter->id) {
            return [];
        }

        $request = Request::create('/reports', 'POST', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'Waasabi demo seeder',
        ]);
        $request->setUserResolver(fn () => $reporter);

        return $moderation->handleReport($request, [
            'content_type' => $contentType,
            'content_id' => (string) $target->getKey(),
            'reason' => $reason,
            'details' => $details,
        ]);
    }
}
