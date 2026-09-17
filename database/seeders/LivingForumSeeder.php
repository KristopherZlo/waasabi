<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\ProjectMember;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LivingForumSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/living_forum.php');
        $users = User::query()->orderBy('id')->get();
        if ($users->isEmpty()) {
            return;
        }

        $covers = ['/images/cover-1.svg', '/images/cover-2.svg', '/images/cover-3.svg', '/images/cover-4.svg'];
        $mediaTypes = [
            'hardware' => 'physical', 'craft' => 'physical', 'performance' => 'video',
            'music' => 'audio', 'photography' => 'image', 'film' => 'video',
            'visual-art' => 'image', 'illustration' => 'image', 'software' => 'software',
            'game' => 'software', 'writing' => 'text', 'research' => 'mixed', 'other' => 'mixed',
        ];

        foreach ($data['projects'] as $index => $entry) {
            $publishedAt = now()->subHours(3 + (($index * 23) % (24 * 42)));
            $body = "## Why this exists\n{$entry['problem']}\n\n## What we built\n{$entry['build']}\n\n## What happened\n{$entry['result']}\n\n## Next step\n{$entry['next']}";
            $post = Post::updateOrCreate(
                ['slug' => $entry['slug']],
                [
                    'user_id' => $users[$index % $users->count()]->id,
                    'type' => 'post',
                    'is_project' => $index % 3 !== 1,
                    'category' => $entry['category'],
                    'media_type' => $mediaTypes[$entry['category']] ?? 'mixed',
                    'license' => $index % 5 === 0 ? 'cc-by' : ($index % 7 === 0 ? 'open-source' : 'all-rights-reserved'),
                    'title' => $entry['title'],
                    'subtitle' => $entry['subtitle'],
                    'body_markdown' => $body,
                    'body_html' => null,
                    'cover_url' => $covers[$index % count($covers)],
                    'album_urls' => $index % 9 === 0 ? [$covers[$index % 4], $covers[($index + 1) % 4]] : null,
                    'status' => $entry['status'],
                    'visibility' => 'public',
                    'published_at' => $publishedAt,
                    'nsfw' => false,
                    'is_hidden' => false,
                    'moderation_status' => 'approved',
                    'tags' => $entry['tags'],
                    'read_time_minutes' => max(1, (int) ceil(str_word_count($body) / 190)),
                ],
            );
            $post->forceFill(['created_at' => $publishedAt, 'updated_at' => $publishedAt->copy()->addHours(2)])->saveQuietly();
        }

        foreach ($data['questions'] as $index => $entry) {
            $publishedAt = now()->subHours(1 + (($index * 17) % (24 * 28)));
            $body = "## Context\n{$entry['context']}\n\n## What I tried\n{$entry['tried']}\n\n## Question\n{$entry['ask']}";
            $question = Post::updateOrCreate(
                ['slug' => $entry['slug']],
                [
                    'user_id' => $users[($index * 3 + 2) % $users->count()]->id,
                    'type' => 'question',
                    'is_project' => false,
                    'category' => 'other',
                    'media_type' => 'text',
                    'license' => 'all-rights-reserved',
                    'title' => $entry['title'],
                    'subtitle' => null,
                    'body_markdown' => $body,
                    'body_html' => null,
                    'cover_url' => null,
                    'album_urls' => null,
                    'status' => null,
                    'visibility' => 'public',
                    'published_at' => $publishedAt,
                    'nsfw' => false,
                    'is_hidden' => false,
                    'moderation_status' => 'approved',
                    'tags' => $entry['tags'],
                    'read_time_minutes' => 1,
                ],
            );
            $question->forceFill(['created_at' => $publishedAt, 'updated_at' => $publishedAt])->saveQuietly();
        }

        Post::query()->whereNull('published_at')->orderBy('id')->get()->each(function (Post $post, int $index): void {
            $publishedAt = now()->subHours(5 + (($index * 31) % (24 * 50)));
            $post->forceFill([
                'published_at' => $publishedAt,
                'created_at' => $publishedAt,
                'updated_at' => $publishedAt->copy()->addHours(1),
            ])->saveQuietly();
        });

        $this->seedActivity($users);
    }

    private function seedActivity(Collection $users): void
    {
        $posts = Post::query()->with('user')->orderBy('id')->get();
        $makers = $users->filter(fn (User $user) => $user->hasRole('maker'))->values();
        $projectComments = [
            'The constraint around :tag feels real. What changed after the first test with people outside the team?',
            'The measured result makes this useful. I would also keep one untouched baseline for the next revision.',
            'This is refreshingly specific. Was the simpler option rejected because of cost, time, or reliability?',
            'The handoff details are strong. A photo of the failed revision would make the trade-off even clearer.',
            'I tried something adjacent on a smaller build and the maintenance step became the real bottleneck.',
            'The next step sounds testable. What result would make you stop iterating and call this finished?',
            'Good scope. I especially like that the limitation is documented instead of hidden behind the final result.',
            'How much of this process would still work if another person had to reproduce it from the notes alone?',
        ];
        $questionComments = [
            'For :tag work, I start with the smallest comparison that can fail clearly, then record the baseline before changing anything.',
            'I would separate the reversible choice from the expensive one. Test the reversible part first and keep the decision log short.',
            'The missing detail for me is frequency: is this a one-off problem or something the team will repeat every week?',
            'A useful rule is to make uncertainty visible. Do not turn a rough measurement into a precise-looking score.',
            'We solved a similar case by testing with one real user early, before polishing the workflow or building automation.',
            'My default would be the boring option with a manual fallback. Add complexity only after the failure is reproduced.',
            'Document the stop condition before the next trial. It prevents a promising test from turning into endless iteration.',
            'If you share one sample or screenshot, it will be easier to distinguish a content problem from a tool problem.',
        ];
        $replyTemplates = [
            'That distinction helps. I will add the baseline and report what changes in the next run.',
            'Good question. The constraint is mostly time, so I can test this without changing the whole build.',
            'I had not separated those cases. I will document them as two different failure modes.',
            'Thanks — the manual fallback is exactly what was missing from the current plan.',
            'This is useful. I can reproduce that suggestion with the material already on hand.',
        ];

        foreach ($posts as $postIndex => $post) {
            $otherUsers = $users->reject(fn (User $user) => $user->id === $post->user_id)->values();
            $commentCount = $postIndex % 13 === 12 ? 0 : ($post->type === 'question' ? 4 + ($postIndex % 4) : 2 + ($postIndex % 4));
            $templates = $post->type === 'question' ? $questionComments : $projectComments;
            $tag = (string) (($post->tags ?? [])[0] ?? 'project');

            for ($commentIndex = 0; $commentIndex < $commentCount; $commentIndex++) {
                $author = $otherUsers[($postIndex * 3 + $commentIndex) % $otherUsers->count()];
                $body = str_replace(':tag', $tag, $templates[($postIndex + $commentIndex) % count($templates)]);
                $createdAt = ($post->published_at ?? $post->created_at ?? now())->copy()->addHours(2 + $commentIndex * 3);
                if ($createdAt->isFuture()) {
                    $createdAt = now()->subMinutes(10 + $commentIndex * 7);
                }
                $comment = PostComment::updateOrCreate([
                    'post_id' => $post->id,
                    'user_id' => $author->id,
                    'body' => $body,
                    'parent_id' => null,
                ], [
                    'post_slug' => $post->slug,
                    'section' => $post->type === 'post' ? ['Context', 'Build', 'Results', 'Next step'][$commentIndex % 4] : null,
                    'useful' => 0,
                    'vote_score' => 0,
                    'moderation_status' => 'approved',
                    'is_hidden' => false,
                ]);
                $comment->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
                $this->seedCommentVotes($comment, $users, $postIndex + $commentIndex);

                if (($postIndex + $commentIndex) % 3 === 0) {
                    $replyAuthor = $commentIndex % 2 === 0
                        ? $post->user
                        : $otherUsers[($postIndex + $commentIndex + 5) % $otherUsers->count()];
                    $reply = PostComment::updateOrCreate([
                        'post_id' => $post->id,
                        'user_id' => $replyAuthor->id,
                        'body' => $replyTemplates[($postIndex + $commentIndex) % count($replyTemplates)],
                        'parent_id' => $comment->id,
                    ], [
                        'post_slug' => $post->slug,
                        'section' => null,
                        'useful' => 0,
                        'vote_score' => 0,
                        'moderation_status' => 'approved',
                        'is_hidden' => false,
                    ]);
                    $replyAt = $createdAt->copy()->addMinutes(35 + $commentIndex * 4);
                    if ($replyAt->isFuture()) {
                        $replyAt = now()->subMinutes(3 + $commentIndex);
                    }
                    $reply->forceFill(['created_at' => $replyAt, 'updated_at' => $replyAt])->saveQuietly();
                    $this->seedCommentVotes($reply, $users, $postIndex + $commentIndex + 2);
                }
            }

            $this->seedPostSignals($post, $users, $postIndex);

            if ($post->type === 'post' && $postIndex % 3 === 0 && $makers->isNotEmpty()) {
                $reviewer = $makers[($postIndex + 2) % $makers->count()];
                if ($reviewer->id === $post->user_id) {
                    $reviewer = $makers[($postIndex + 3) % $makers->count()];
                }
                $review = PostReview::updateOrCreate([
                    'post_id' => $post->id,
                    'user_id' => $reviewer->id,
                    'improve' => 'Show one comparison from before the final approach, including the option that looked promising but failed.',
                ], [
                    'post_slug' => $post->slug,
                    'why' => 'That evidence would help another maker understand the decision instead of copying the result blindly.',
                    'how' => 'Add a compact before-and-after block with one photograph or measurement and a sentence about the trade-off.',
                    'vote_score' => 0,
                    'moderation_status' => 'approved',
                    'is_hidden' => false,
                ]);
                $reviewAt = ($post->published_at ?? now())->copy()->addDay();
                if ($reviewAt->isFuture()) {
                    $reviewAt = now()->subMinutes(20 + $postIndex);
                }
                $review->forceFill(['created_at' => $reviewAt, 'updated_at' => $reviewAt])->saveQuietly();
                $this->seedReviewVotes($review, $users, $postIndex);
            }

            if ($post->type === 'post' && $post->is_project && $postIndex % 5 === 0) {
                $update = ProjectUpdate::updateOrCreate([
                    'post_id' => $post->id,
                    'title' => ['Field test completed', 'Second revision assembled', 'Documentation updated'][$postIndex % 3],
                ], [
                    'user_id' => $post->user_id,
                    'body' => ['The latest test confirmed the main assumption and exposed one smaller maintenance issue.', 'The new revision is easier to assemble and keeps the same measured performance.', 'Build notes now include the failed option, exact settings, and a shorter reproduction checklist.'][$postIndex % 3],
                ]);
                $updateAt = ($post->published_at ?? now())->copy()->addDays(2);
                if ($updateAt->isFuture()) {
                    $updateAt = now()->subMinutes(30 + $postIndex);
                }
                $update->forceFill(['created_at' => $updateAt, 'updated_at' => $updateAt])->saveQuietly();
            }

            if ($post->type === 'post' && $post->is_project && $postIndex % 8 === 0) {
                $member = $otherUsers[($postIndex + 7) % $otherUsers->count()];
                ProjectMember::updateOrCreate(
                    ['post_id' => $post->id, 'user_id' => $member->id],
                    [
                        'invited_by' => $post->user_id,
                        'role' => ['designer', 'developer', 'researcher', 'contributor'][$postIndex % 4],
                        'status' => 'active',
                        'can_edit' => true,
                        'accepted_at' => ($post->published_at ?? now())->copy()->addDay(),
                    ],
                );
            }
        }

        $this->seedSocialGraph($users, $posts);
    }

    private function seedCommentVotes(PostComment $comment, Collection $users, int $seed): void
    {
        $score = 0;
        $voters = $users->reject(fn (User $user) => $user->id === $comment->user_id)->values();
        $voteCount = 1 + ($seed % 4);
        for ($index = 0; $index < $voteCount; $index++) {
            $voter = $voters[($seed + $index * 3) % $voters->count()];
            $value = $seed % 13 === 0 && $index === $voteCount - 1 ? -1 : 1;
            DB::table('post_comment_votes')->insertOrIgnore([
                'post_comment_id' => $comment->id,
                'user_id' => $voter->id,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $score += $value;
        }
        $comment->update(['vote_score' => $score, 'useful' => max(0, $score)]);
    }

    private function seedReviewVotes(PostReview $review, Collection $users, int $seed): void
    {
        $score = 0;
        $voters = $users->reject(fn (User $user) => $user->id === $review->user_id)->values();
        for ($index = 0; $index < 2 + ($seed % 4); $index++) {
            $voter = $voters[($seed + $index * 2) % $voters->count()];
            DB::table('post_review_votes')->insertOrIgnore([
                'post_review_id' => $review->id,
                'user_id' => $voter->id,
                'value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $score++;
        }
        $review->update(['vote_score' => $score]);
    }

    private function seedPostSignals(Post $post, Collection $users, int $seed): void
    {
        $eligible = $users->reject(fn (User $user) => $user->id === $post->user_id)->values();
        for ($index = 0; $index < 3 + ($seed % 9); $index++) {
            $user = $eligible[($seed + $index * 2) % $eligible->count()];
            DB::table('post_upvotes')->insertOrIgnore([
                'user_id' => $user->id,
                'post_id' => $post->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        for ($index = 0; $index < 1 + ($seed % 4); $index++) {
            $user = $eligible[($seed + $index * 5 + 1) % $eligible->count()];
            DB::table('post_saves')->insertOrIgnore([
                'user_id' => $user->id,
                'post_id' => $post->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedSocialGraph(Collection $users, Collection $posts): void
    {
        foreach ($users as $userIndex => $user) {
            for ($offset = 1; $offset <= 3; $offset++) {
                $followed = $users[($userIndex + $offset * 4) % $users->count()];
                if ($followed->id !== $user->id) {
                    DB::table('user_follows')->insertOrIgnore([
                        'follower_id' => $user->id,
                        'following_id' => $followed->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            foreach ([0, 7] as $offset) {
                $post = $posts[($userIndex * 5 + $offset) % $posts->count()];
                if ($post->user_id === $user->id) {
                    continue;
                }
                DB::table('reading_progress')->updateOrInsert(
                    ['user_id' => $user->id, 'post_id' => $post->id],
                    [
                        'percent' => 18 + (($userIndex * 13 + $offset * 7) % 76),
                        'anchor' => ['context', 'build', 'results', 'next-step'][($userIndex + $offset) % 4],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            $notificationPost = $posts[($userIndex * 7 + 3) % $posts->count()];
            $notification = UserNotification::updateOrCreate([
                'user_id' => $user->id,
                'type' => ['Comment', 'Review', 'Follow', 'Project update'][$userIndex % 4],
                'text' => [
                    "A new discussion started under “{$notificationPost->title}”.",
                    "A maker left a structured review on “{$notificationPost->title}”.",
                    'Someone whose work you saved started following your projects.',
                    "There is a new build update in “{$notificationPost->title}”.",
                ][$userIndex % 4],
            ], [
                'link' => $notificationPost->type === 'question'
                    ? route('questions.show', $notificationPost->slug)
                    : route('project', $notificationPost->slug),
                'read_at' => $userIndex % 3 === 0 ? now()->subHours(2) : null,
            ]);
            $notificationAt = now()->subMinutes(12 + $userIndex * 19);
            $notification->forceFill(['created_at' => $notificationAt, 'updated_at' => $notificationAt])->saveQuietly();
        }

        foreach ($users->take(10) as $index => $user) {
            UserBadge::firstOrCreate(
                ['user_id' => $user->id, 'badge_key' => ['beta', 'contributor', 'reference_builder', 'bug_hunter'][$index % 4]],
                ['reason' => 'Active contributor in the seeded community snapshot.', 'issued_at' => now()->subDays(5 + $index)],
            );
        }
    }
}
