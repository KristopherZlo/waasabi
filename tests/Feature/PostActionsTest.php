<?php

namespace Tests\Feature;

use App\Models\ContentReport;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class PostActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function makeEligibleUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'created_at' => now()->subMinutes(20),
        ], $attributes));
    }

    public function test_author_can_access_edit_page(): void
    {
        $author = $this->makeEligibleUser();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'type' => 'post',
        ]);

        $this->actingAs($author)
            ->get('/posts/'.$post->slug.'/edit')
            ->assertOk();
    }

    public function test_non_owner_cannot_access_edit_page(): void
    {
        $author = $this->makeEligibleUser();
        $other = $this->makeEligibleUser();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'type' => 'post',
        ]);

        $this->actingAs($other)
            ->get('/posts/'.$post->slug.'/edit')
            ->assertForbidden();
    }

    public function test_owner_can_delete_post(): void
    {
        $author = $this->makeEligibleUser();
        $reporter = $this->makeEligibleUser();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'type' => 'post',
        ]);
        $report = ContentReport::create([
            'user_id' => $reporter->id,
            'content_type' => 'post',
            'content_id' => (string) $post->id,
            'reason' => 'spam',
        ]);
        $comment = PostComment::factory()->create([
            'post_id' => $post->id,
            'post_slug' => $post->slug,
        ]);
        $commentReport = ContentReport::create([
            'user_id' => $reporter->id,
            'content_type' => 'comment',
            'content_id' => (string) $comment->id,
            'reason' => 'spam',
        ]);

        $this->actingAs($author)
            ->deleteJson('/posts/'.$post->id)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertSame('withdrawn', $report->fresh()->resolved_status);
        $this->assertSame('withdrawn', $commentReport->fresh()->resolved_status);
    }

    public function test_non_owner_cannot_delete_post(): void
    {
        $author = $this->makeEligibleUser();
        $other = $this->makeEligibleUser();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'type' => 'post',
        ]);

        $this->actingAs($other)
            ->deleteJson('/posts/'.$post->id)
            ->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_admin_must_use_the_audited_admin_delete_endpoint(): void
    {
        $author = $this->makeEligibleUser();
        $admin = $this->makeEligibleUser(['role' => 'admin']);
        $post = Post::factory()->for($author)->create();

        $this->actingAs($admin)->deleteJson(route('posts.delete', $post))->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }
}
