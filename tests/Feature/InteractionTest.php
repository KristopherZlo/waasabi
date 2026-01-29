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

class InteractionTest extends TestCase
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

    public function test_guest_cannot_comment(): void
    {
        $response = $this->postJson('/projects/power-hub-night/comments', [
            'body' => 'Guest comment',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_comment_on_project(): void
    {
        $user = $this->makeEligibleUser();
        Post::factory()->create([
            'slug' => 'power-hub-night',
            'type' => 'post',
        ]);

        $response = $this->actingAs($user)->postJson('/projects/power-hub-night/comments', [
            'body' => 'Great write-up.',
            'section' => 'Context',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('post_comments', [
            'post_slug' => 'power-hub-night',
            'user_id' => $user->id,
            'body' => 'Great write-up.',
        ]);
    }

    public function test_user_can_reply_to_comment(): void
    {
        $user = $this->makeEligibleUser();
        Post::factory()->create([
            'slug' => 'power-hub-night',
            'type' => 'post',
        ]);
        $parent = PostComment::create([
            'post_slug' => 'power-hub-night',
            'user_id' => $user->id,
            'body' => 'Parent comment',
            'section' => null,
            'useful' => 0,
            'parent_id' => null,
        ]);

        $response = $this->actingAs($user)->postJson('/projects/power-hub-night/comments', [
            'body' => 'Reply comment',
            'parent_id' => $parent->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('post_comments', [
            'parent_id' => $parent->id,
            'body' => 'Reply comment',
        ]);
    }

    public function test_comment_parent_must_match_slug(): void
    {
        $user = $this->makeEligibleUser();
        Post::factory()->create([
            'slug' => 'field-notes',
            'type' => 'post',
        ]);
        Post::factory()->create([
            'slug' => 'power-hub-night',
            'type' => 'post',
        ]);
        $parent = PostComment::create([
            'post_slug' => 'power-hub-night',
            'user_id' => $user->id,
            'body' => 'Parent comment',
            'section' => null,
            'useful' => 0,
            'parent_id' => null,
        ]);

        $response = $this->actingAs($user)->postJson('/projects/field-notes/comments', [
            'body' => 'Reply comment',
            'parent_id' => $parent->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_comment_rejects_unknown_slug(): void
    {
        $user = $this->makeEligibleUser();

        $response = $this->actingAs($user)->postJson('/projects/unknown-slug/comments', [
            'body' => 'Invalid slug',
        ]);

        $response->assertStatus(404);
    }

    public function test_user_cannot_review_without_maker_role(): void
    {
        $user = $this->makeEligibleUser(['role' => 'user']);
        Post::factory()->create([
            'slug' => 'power-hub-night',
            'type' => 'post',
        ]);

        $response = $this->actingAs($user)->postJson('/projects/power-hub-night/reviews', [
            'improve' => 'Add a comparison table.',
            'why' => 'It helps readers.',
            'how' => 'Insert a small table after measurements.',
        ]);

        $response->assertStatus(403);
    }

    public function test_maker_can_review_project(): void
    {
        $user = $this->makeEligibleUser(['role' => 'maker']);
        Post::factory()->create([
            'slug' => 'power-hub-night',
            'type' => 'post',
        ]);

        $response = $this->actingAs($user)->postJson('/projects/power-hub-night/reviews', [
            'improve' => 'Add a comparison table.',
            'why' => 'It helps readers.',
            'how' => 'Insert a small table after measurements.',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('post_reviews', [
            'post_slug' => 'power-hub-night',
            'user_id' => $user->id,
            'improve' => 'Add a comparison table.',
        ]);
    }

    public function test_user_can_toggle_save(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['type' => 'post']);

        $response = $this->actingAs($user)->postJson("/posts/{$post->slug}/save");
        $response->assertOk()->assertJson(['saved' => true]);
        $this->assertDatabaseHas('post_saves', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $response = $this->actingAs($user)->postJson("/posts/{$post->slug}/save");
        $response->assertOk()->assertJson(['saved' => false]);
        $this->assertDatabaseMissing('post_saves', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_user_can_toggle_upvote(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['type' => 'post']);

        $response = $this->actingAs($user)->postJson("/posts/{$post->slug}/upvote");
        $response->assertOk()->assertJson(['upvoted' => true]);
        $this->assertDatabaseHas('post_upvotes', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $response = $this->actingAs($user)->postJson("/posts/{$post->slug}/upvote");
        $response->assertOk()->assertJson(['upvoted' => false]);
        $this->assertDatabaseMissing('post_upvotes', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_reading_progress_is_saved_for_user(): void
    {
        $user = $this->makeEligibleUser();
        Post::factory()->create([
            'slug' => 'power-hub-night',
            'type' => 'post',
        ]);

        $response = $this->actingAs($user)->postJson('/reading-progress', [
            'post_id' => 'power-hub-night',
            'percent' => 55,
            'anchor' => 'context',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        if (Schema::hasTable('reading_progress')) {
            $this->assertDatabaseHas('reading_progress', [
                'user_id' => $user->id,
                'post_id' => 'power-hub-night',
                'percent' => 55,
            ]);
        }
    }

    public function test_reading_progress_skips_unknown_slug(): void
    {
        $user = $this->makeEligibleUser();

        $response = $this->actingAs($user)->postJson('/reading-progress', [
            'post_id' => 'unknown-slug',
            'percent' => 10,
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseMissing('reading_progress', [
            'user_id' => $user->id,
            'post_id' => 'unknown-slug',
        ]);
    }

    public function test_report_can_be_submitted(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/reports', [
            'content_type' => 'post',
            'content_id' => 'power-hub-night',
            'content_url' => 'http://localhost/projects/power-hub-night',
            'reason' => 'spam',
            'details' => 'Looks suspicious.',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('content_reports', [
            'content_type' => 'post',
            'reason' => 'spam',
        ]);
    }

    public function test_report_validation_rejects_invalid_reason(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/reports', [
            'content_type' => 'post',
            'content_id' => 'power-hub-night',
            'content_url' => 'http://localhost/projects/power-hub-night',
            'reason' => 'invalid',
        ]);

        $response->assertStatus(422);
    }

    public function test_guest_cannot_submit_report(): void
    {
        $response = $this->postJson('/reports', [
            'content_type' => 'post',
            'content_id' => 'power-hub-night',
            'content_url' => 'http://localhost/projects/power-hub-night',
            'reason' => 'spam',
        ]);

        $response->assertStatus(401);
    }
}
