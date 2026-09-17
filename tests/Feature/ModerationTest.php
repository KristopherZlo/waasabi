<?php

namespace Tests\Feature;

use App\Models\ContentReport;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\User;
use App\Services\ContentModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
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

    public function test_moderator_can_hide_and_restore_post(): void
    {
        $moderator = $this->makeModerator();
        $author = $this->makeUser();
        $post = Post::factory()->create(['user_id' => $author->id]);

        $hide = $this->actingAs($moderator)
            ->postJson('/admin/moderation/posts/'.$post->id.'/hide', ['reason' => 'Test']);
        $hide->assertOk()->assertJson(['ok' => true]);

        $post->refresh();
        $this->assertSame('hidden', $post->moderation_status);

        $restore = $this->actingAs($moderator)
            ->postJson('/admin/moderation/posts/'.$post->id.'/restore');
        $restore->assertOk()->assertJson(['ok' => true]);

        $post->refresh();
        $this->assertSame('approved', $post->moderation_status);
    }

    public function test_moderator_cannot_hide_admin_post(): void
    {
        $moderator = $this->makeModerator();
        $admin = $this->makeUser(['role' => 'admin']);
        $post = Post::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($moderator)
            ->postJson('/admin/moderation/posts/'.$post->id.'/hide', ['reason' => 'Test'])
            ->assertStatus(403);

        $post->update(['moderation_status' => 'hidden', 'is_hidden' => true]);

        $this->actingAs($moderator)
            ->postJson('/admin/moderation/posts/'.$post->id.'/restore')
            ->assertStatus(403);

        $this->assertSame('hidden', $post->fresh()->moderation_status);
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
    }

    public function test_moderator_can_hide_review(): void
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
            ->postJson('/admin/moderation/reviews/'.$review->id.'/hide', ['reason' => 'Test']);
        $response->assertOk()->assertJson(['ok' => true]);

        $review->refresh();
        $this->assertSame('hidden', $review->moderation_status);
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
