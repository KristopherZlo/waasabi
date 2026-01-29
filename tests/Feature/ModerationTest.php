<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Schema;
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
            ->postJson('/admin/moderation/posts/' . $post->id . '/hide', ['reason' => 'Test']);
        $hide->assertOk()->assertJson(['ok' => true]);

        if (Schema::hasColumn('posts', 'moderation_status')) {
            $post->refresh();
            $this->assertSame('hidden', $post->moderation_status);
        }

        $restore = $this->actingAs($moderator)
            ->postJson('/admin/moderation/posts/' . $post->id . '/restore');
        $restore->assertOk()->assertJson(['ok' => true]);

        if (Schema::hasColumn('posts', 'moderation_status')) {
            $post->refresh();
            $this->assertSame('approved', $post->moderation_status);
        }
    }

    public function test_moderator_cannot_hide_admin_post(): void
    {
        $moderator = $this->makeModerator();
        $admin = $this->makeUser(['role' => 'admin']);
        $post = Post::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($moderator)
            ->postJson('/admin/moderation/posts/' . $post->id . '/hide', ['reason' => 'Test'])
            ->assertStatus(403);
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
            ->postJson('/admin/moderation/comments/' . $comment->id . '/queue', ['reason' => 'Test']);
        $response->assertOk()->assertJson(['ok' => true]);

        if (Schema::hasColumn('post_comments', 'moderation_status')) {
            $comment->refresh();
            $this->assertSame('pending', $comment->moderation_status);
        }
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
            ->postJson('/admin/moderation/reviews/' . $review->id . '/hide', ['reason' => 'Test']);
        $response->assertOk()->assertJson(['ok' => true]);

        if (Schema::hasColumn('post_reviews', 'moderation_status')) {
            $review->refresh();
            $this->assertSame('hidden', $review->moderation_status);
        }
    }
}
