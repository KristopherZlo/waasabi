<?php

namespace Tests\Feature;

use App\Models\ContentReport;
use App\Models\ContentReportScore;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\User;
use App\Services\AutoModerationService;
use App\Services\ContentModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function makeModerator(): User
    {
        return User::factory()->create([
            'role' => 'moderator',
            'email_verified_at' => now(),
            'created_at' => now()->subMinutes(20),
        ]);
    }

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'created_at' => now()->subMinutes(20),
        ], $attributes));
    }

    public function test_admin_can_hide_and_restore_post(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $author = $this->makeUser();
        $post = Post::factory()->create(['user_id' => $author->id]);

        $hide = $this->actingAs($admin)
            ->postJson('/admin/moderation/posts/'.$post->id.'/hide', ['reason' => 'Test']);
        $hide->assertOk()->assertJson(['ok' => true]);

        $post->refresh();
        $this->assertSame('hidden', $post->moderation_status);

        $restore = $this->actingAs($admin)
            ->postJson('/admin/moderation/posts/'.$post->id.'/restore');
        $restore->assertOk()->assertJson(['ok' => true]);

        $post->refresh();
        $this->assertSame('approved', $post->moderation_status);
    }

    public function test_admin_can_mark_and_unmark_reported_post_nsfw(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $post = Post::factory()->create();
        $report = ContentReport::create([
            'user_id' => $this->makeUser()->id,
            'content_type' => $post->type,
            'content_id' => (string) $post->id,
            'reason' => 'other',
            'weight' => 1,
            'resolved_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->postJson(route('moderation.posts.nsfw', $post), ['nsfw' => true])
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('nsfw', true);

        $this->assertTrue($post->fresh()->nsfw);
        $this->assertSame('confirmed', $report->fresh()->resolved_status);

        $this->actingAs($admin)
            ->postJson(route('moderation.posts.nsfw', $post), ['nsfw' => false])
            ->assertOk()
            ->assertJsonPath('nsfw', false);

        $this->assertFalse($post->fresh()->nsfw);
    }

    public function test_moderator_can_only_send_post_to_review(): void
    {
        $moderator = $this->makeModerator();
        $author = $this->makeUser();
        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($moderator)
            ->postJson('/admin/moderation/posts/'.$post->id.'/queue', ['reason' => 'Needs an administrator review'])
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $post->refresh();
        $this->assertSame('pending', $post->moderation_status);
        $this->assertTrue($post->is_hidden);
        $this->assertDatabaseHas('content_reports', [
            'user_id' => null,
            'content_type' => 'post',
            'content_id' => (string) $post->id,
            'reason' => 'admin_flag',
            'details' => 'Needs an administrator review',
            'resolved_status' => 'pending',
        ]);

        $this->actingAs($moderator)
            ->postJson('/admin/moderation/posts/'.$post->id.'/hide', ['reason' => 'Test'])
            ->assertForbidden();

        $this->actingAs($moderator)
            ->postJson('/admin/moderation/posts/'.$post->id.'/restore')
            ->assertForbidden();

        $this->actingAs($moderator)->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.data.0.key', 'post:'.$post->id)
                ->where('items.data.0.content_status', 'pending')
                ->where('items.data.0.hidden', true));
    }

    public function test_moderator_can_queue_comment(): void
    {
        $moderator = $this->makeModerator();
        $author = $this->makeUser();
        $post = Post::factory()->create(['user_id' => $author->id]);
        $comment = PostComment::create([
            'post_slug' => $post->slug,
            'user_id' => $author->id,
            'body' => 'Comment body',
            'section' => null,
            'useful' => 0,
            'parent_id' => null,
        ]);

        $response = $this->actingAs($moderator)
            ->postJson('/admin/moderation/comments/'.$comment->id.'/queue', ['reason' => 'Test']);
        $response->assertOk()->assertJson(['ok' => true]);

        $comment->refresh();
        $this->assertSame('pending', $comment->moderation_status);
        $this->assertTrue($comment->is_hidden);
        $this->assertDatabaseHas('content_reports', [
            'content_type' => 'comment',
            'content_id' => (string) $comment->id,
            'resolved_status' => 'pending',
        ]);
    }

    public function test_moderator_can_queue_but_not_hide_review(): void
    {
        $moderator = $this->makeModerator();
        $author = $this->makeUser(['role' => 'maker']);
        $post = Post::factory()->create(['user_id' => $author->id]);
        $review = PostReview::create([
            'post_slug' => $post->slug,
            'user_id' => $author->id,
            'improve' => 'Improve',
            'why' => 'Why',
            'how' => 'How',
        ]);

        $response = $this->actingAs($moderator)
            ->postJson('/admin/moderation/reviews/'.$review->id.'/queue', ['reason' => 'Test']);
        $response->assertOk()->assertJson(['ok' => true]);

        $review->refresh();
        $this->assertSame('pending', $review->moderation_status);
        $this->assertTrue($review->is_hidden);

        $this->actingAs($moderator)
            ->postJson('/admin/moderation/reviews/'.$review->id.'/hide', ['reason' => 'Test'])
            ->assertForbidden();
    }

    public function test_moderation_feed_groups_reported_posts_and_comments(): void
    {
        $moderator = $this->makeModerator();
        $author = $this->makeUser();
        $reporter = $this->makeUser();
        $post = Post::factory()->create(['user_id' => $author->id]);
        $comment = PostComment::create([
            'post_slug' => $post->slug,
            'user_id' => $author->id,
            'body' => 'A reported comment',
            'useful' => 0,
        ]);
        foreach ([['post', $post->id, 2.5], ['comment', $comment->id, 1.5]] as [$type, $id, $weight]) {
            ContentReport::create([
                'user_id' => $reporter->id,
                'content_type' => $type,
                'content_id' => (string) $id,
                'reason' => 'spam',
                'weight' => $weight,
                'resolved_status' => 'pending',
            ]);
        }

        $this->actingAs($moderator)->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Moderation')
                ->has('items.data', 2)
                ->where('items.data.0.type', 'post')
                ->where('items.data.1.type', 'comment'));

        $this->actingAs($this->makeUser())->get('/admin')->assertForbidden();
    }

    public function test_staff_content_payload_exposes_quick_action_permissions_and_state(): void
    {
        $moderator = $this->makeModerator();
        $post = Post::factory()->create();
        $comment = PostComment::create([
            'post_slug' => $post->slug,
            'user_id' => $post->user_id,
            'body' => 'Visible moderation target',
            'useful' => 0,
        ]);

        $this->actingAs($moderator)->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.moderator', true)
                ->where('auth.user.admin', false)
                ->where('works.data.0.moderation_status', 'approved')
                ->where('works.data.0.is_hidden', false));

        $this->actingAs($moderator)->get(route('project', $post->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('comments.data.0.id', $comment->id)
                ->where('comments.data.0.moderation_status', 'approved')
                ->where('comments.data.0.is_hidden', false));

        $admin = $this->makeUser(['role' => 'admin']);
        $this->actingAs($admin)->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.admin', true));
    }

    public function test_auto_hidden_post_waits_for_and_accepts_admin_verdict(): void
    {
        config([
            'moderation.reports.auto_hide.base_threshold' => 1,
            'moderation.reports.auto_hide.minimum_reports' => 3,
            'moderation.reports.site_scale.min_scale' => 1,
            'moderation.reports.site_scale.max_scale' => 1,
        ]);
        $author = $this->makeUser();
        $post = Post::factory()->create(['user_id' => $author->id]);
        foreach (range(1, 3) as $number) {
            $reporter = $this->makeUser(['created_at' => now()->subDays(30)]);
            $this->actingAs($reporter)->postJson('/reports', [
                'content_type' => 'post',
                'content_id' => (string) $post->id,
                'reason' => 'spam',
                'details' => 'Report '.$number,
            ])->assertOk();
        }

        $this->assertTrue($post->fresh()->is_hidden);
        $this->assertSame('auto_hidden', ContentReport::query()->value('resolved_status'));

        $admin = $this->makeUser(['role' => 'admin']);
        $this->actingAs($admin)->postJson('/admin/moderation/posts/'.$post->id.'/restore')->assertOk();

        $this->assertFalse($post->fresh()->is_hidden);
        $this->assertSame(3, ContentReport::query()->where('resolved_status', 'rejected')->count());
    }

    public function test_empty_new_accounts_cannot_inflate_the_auto_hide_threshold(): void
    {
        config([
            'moderation.reports.site_scale.baseline_active_users' => 1,
            'moderation.reports.site_scale.baseline_content' => 1,
            'moderation.reports.site_scale.sensitivity' => 1,
            'moderation.reports.site_scale.min_scale' => 0.5,
            'moderation.reports.site_scale.max_scale' => 2,
        ]);
        $author = $this->makeUser(['created_at' => now()->subDays(30)]);
        $reporterA = $this->makeUser(['created_at' => now()->subDays(30)]);
        $reporterB = $this->makeUser(['created_at' => now()->subDays(30)]);
        $postA = Post::factory()->create(['user_id' => $author->id]);
        $postB = Post::factory()->create(['user_id' => $author->id]);

        $thresholdBefore = $this->actingAs($reporterA)->postJson('/reports', [
            'content_type' => 'post',
            'content_id' => (string) $postA->id,
            'reason' => 'spam',
        ])->assertOk()->json('weight_threshold');

        User::factory()->count(100)->create(['created_at' => now()]);

        $thresholdAfter = $this->actingAs($reporterB)->postJson('/reports', [
            'content_type' => 'post',
            'content_id' => (string) $postB->id,
            'reason' => 'spam',
        ])->assertOk()->json('weight_threshold');

        $this->assertEqualsWithDelta($thresholdBefore, $thresholdAfter, 0.001);
    }

    public function test_one_hundred_new_accounts_cannot_auto_hide_content(): void
    {
        config([
            'moderation.reports.site_scale.min_scale' => 1,
            'moderation.reports.site_scale.max_scale' => 1,
        ]);
        $post = Post::factory()->for($this->makeUser(['created_at' => now()->subDays(30)]))->create();
        $moderation = app(AutoModerationService::class);

        User::factory()->count(100)->create(['created_at' => now()])->each(function (User $reporter) use ($moderation, $post): void {
            $request = Request::create('/reports', 'POST');
            $request->setUserResolver(fn () => $reporter);
            $moderation->handleReport($request, [
                'content_type' => 'post',
                'content_id' => (string) $post->id,
                'reason' => 'spam',
            ]);
        });

        $score = ContentReportScore::query()->where('content_type', 'post')->where('content_id', (string) $post->id)->firstOrFail();
        $this->assertSame(100, $score->reports_count);
        $this->assertEqualsWithDelta(5.0, $score->weight_total, 0.01);
        $this->assertFalse($post->fresh()->is_hidden);
    }

    public function test_flagged_uploaded_image_enters_the_media_queue(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/editor/flagged.jpg', 'image');
        $user = $this->makeUser();
        $service = new class extends ContentModerationService
        {
            public function scanImageForSexualContent(string $absolutePath): array
            {
                return [
                    'status' => 'ok',
                    'flagged' => true,
                    'labels' => [['name' => 'Explicit Nudity', 'confidence' => 99.2]],
                    'reason' => null,
                ];
            }
        };

        $result = $service->moderateUploadedImage('storage/uploads/editor/flagged.jpg', $user, 'editor');

        $this->assertTrue($result['review_requested']);
        $report = ContentReport::where('content_type', 'content')->firstOrFail();
        $this->assertSame('storage/uploads/editor/flagged.jpg', $report->content_id);
        $this->assertSame('pending', $report->resolved_status);
        $this->assertStringContainsString('Explicit Nudity', $report->details);
    }

    public function test_image_moderation_rejects_paths_outside_the_upload_directory(): void
    {
        $result = app(ContentModerationService::class)
            ->moderateUploadedImage('storage/uploads/../../.env', $this->makeUser(), 'editor');

        $this->assertSame('error', $result['status']);
        $this->assertSame('invalid_path', $result['reason']);
        $this->assertDatabaseCount('content_reports', 0);
    }
}
