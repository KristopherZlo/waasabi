<?php

namespace Tests\Feature;

use App\Models\ContentReport;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
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

    public function test_reply_to_a_reply_stays_in_the_visible_thread(): void
    {
        $user = $this->makeEligibleUser();
        Post::factory()->create([
            'slug' => 'power-hub-night',
            'type' => 'post',
        ]);
        $parent = PostComment::factory()->create([
            'post_slug' => 'power-hub-night',
            'user_id' => $user->id,
            'parent_id' => null,
        ]);
        $reply = PostComment::factory()->create([
            'post_slug' => 'power-hub-night',
            'user_id' => $user->id,
            'parent_id' => $parent->id,
        ]);

        $this->actingAs($user)->postJson('/projects/power-hub-night/comments', [
            'body' => 'Visible nested reply',
            'parent_id' => $reply->id,
        ])->assertOk()->assertJsonPath('parent_id', $parent->id)->assertJsonPath('reply_to_id', $reply->id);

        $this->assertDatabaseHas('post_comments', [
            'parent_id' => $parent->id,
            'reply_to_id' => $reply->id,
            'body' => 'Visible nested reply',
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

    public function test_comment_vote_is_persistent_and_toggles(): void
    {
        $user = $this->makeEligibleUser();
        $post = Post::factory()->create(['type' => 'post']);
        $comment = PostComment::factory()->create([
            'post_id' => $post->id,
            'post_slug' => $post->slug,
            'vote_score' => 0,
        ]);

        $this->actingAs($user)->putJson("/comments/{$comment->id}/vote", ['value' => 1])
            ->assertOk()->assertJson(['score' => 1, 'vote' => 1]);
        $this->assertDatabaseHas('post_comment_votes', [
            'post_comment_id' => $comment->id,
            'user_id' => $user->id,
            'value' => 1,
        ]);

        $this->actingAs($user)->putJson("/comments/{$comment->id}/vote", ['value' => 1])
            ->assertOk()->assertJson(['score' => 0, 'vote' => 0]);
        $this->assertDatabaseMissing('post_comment_votes', [
            'post_comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);
        $this->assertSame(0, $comment->fresh()->vote_score);
    }

    public function test_review_vote_can_change_direction(): void
    {
        $user = $this->makeEligibleUser();
        $post = Post::factory()->create(['type' => 'post']);
        $review = PostReview::factory()->create([
            'post_id' => $post->id,
            'post_slug' => $post->slug,
            'vote_score' => 0,
        ]);

        $this->actingAs($user)->putJson("/reviews/{$review->id}/vote", ['value' => 1])
            ->assertOk()->assertJson(['score' => 1, 'vote' => 1]);
        $this->actingAs($user)->putJson("/reviews/{$review->id}/vote", ['value' => -1])
            ->assertOk()->assertJson(['score' => -1, 'vote' => -1]);

        $this->assertDatabaseHas('post_review_votes', [
            'post_review_id' => $review->id,
            'user_id' => $user->id,
            'value' => -1,
        ]);
        $this->assertSame(-1, $review->fresh()->vote_score);
    }

    public function test_hidden_interactions_cannot_be_voted_on(): void
    {
        $user = $this->makeEligibleUser();
        $post = Post::factory()->create(['type' => 'post']);
        $comment = PostComment::factory()->create([
            'post_id' => $post->id,
            'post_slug' => $post->slug,
            'is_hidden' => true,
            'moderation_status' => 'pending',
        ]);

        $this->actingAs($user)
            ->putJson("/comments/{$comment->id}/vote", ['value' => 1])
            ->assertNotFound();
        $this->assertDatabaseMissing('post_comment_votes', [
            'post_comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_owner_can_update_and_delete_comment(): void
    {
        $owner = $this->makeEligibleUser();
        $other = $this->makeEligibleUser();
        $post = Post::factory()->create(['type' => 'post']);
        $comment = PostComment::factory()->create([
            'post_id' => $post->id,
            'post_slug' => $post->slug,
            'user_id' => $owner->id,
        ]);
        $report = ContentReport::create([
            'user_id' => $other->id,
            'content_type' => 'comment',
            'content_id' => (string) $comment->id,
            'reason' => 'spam',
        ]);

        $this->actingAs($other)->patchJson("/comments/{$comment->id}", ['body' => 'Hijacked'])
            ->assertForbidden();
        $this->actingAs($owner)->patchJson("/comments/{$comment->id}", ['body' => 'A clearer response'])
            ->assertOk();
        $this->assertDatabaseHas('post_comments', ['id' => $comment->id, 'body' => 'A clearer response']);

        $this->actingAs($owner)->deleteJson("/comments/{$comment->id}")->assertOk();
        $this->assertDatabaseMissing('post_comments', ['id' => $comment->id]);
        $this->assertSame('withdrawn', $report->fresh()->resolved_status);
    }

    public function test_owner_can_update_and_delete_review(): void
    {
        $owner = $this->makeEligibleUser(['role' => 'maker']);
        $post = Post::factory()->create(['type' => 'post']);
        $review = PostReview::factory()->create([
            'post_id' => $post->id,
            'post_slug' => $post->slug,
            'user_id' => $owner->id,
        ]);
        $payload = [
            'improve' => 'Add captions to the process images.',
            'why' => 'The important changes are otherwise easy to miss.',
            'how' => 'Use one short caption beneath every image.',
        ];

        $this->actingAs($owner)->patchJson("/reviews/{$review->id}", $payload)->assertOk();
        $this->assertDatabaseHas('post_reviews', ['id' => $review->id, 'improve' => $payload['improve']]);
        $this->actingAs($owner)->deleteJson("/reviews/{$review->id}")->assertOk();
        $this->assertDatabaseMissing('post_reviews', ['id' => $review->id]);
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
        $this->assertDatabaseHas('reading_progress', [
            'user_id' => $user->id,
            'post_id' => 'power-hub-night',
            'percent' => 55,
        ]);
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

    public function test_reading_progress_skips_a_draft_the_user_cannot_view(): void
    {
        $user = $this->makeEligibleUser();
        $owner = $this->makeEligibleUser();
        $draft = Post::factory()->for($owner)->create(['visibility' => 'draft']);

        $this->actingAs($user)->postJson('/reading-progress', [
            'post_id' => $draft->slug,
            'percent' => 80,
        ])->assertOk();

        $this->assertDatabaseMissing('reading_progress', [
            'user_id' => $user->id,
            'post_id' => $draft->slug,
        ]);
        $this->assertDatabaseMissing('reading_activity', ['post_id' => $draft->slug]);
    }

    public function test_comment_chunk_rejects_a_draft_the_user_cannot_view(): void
    {
        $viewer = $this->makeEligibleUser();
        $owner = $this->makeEligibleUser();
        $draft = Post::factory()->for($owner)->create(['visibility' => 'draft']);
        PostComment::factory()->for($owner, 'user')->create([
            'post_id' => $draft->id,
            'post_slug' => $draft->slug,
        ]);

        $this->actingAs($viewer)
            ->getJson(route('project.comments.chunk', $draft->slug))
            ->assertNotFound();
    }

    public function test_report_can_be_submitted(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->create(['slug' => 'report-target']);

        $response = $this->actingAs($user)->postJson('/reports', [
            'content_type' => 'post',
            'content_id' => $post->slug,
            'content_url' => 'https://phishing.example/moderator-login',
            'reason' => 'spam',
            'details' => 'Looks suspicious.',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('content_reports', [
            'content_type' => 'post',
            'content_id' => (string) $post->id,
            'content_url' => route('project', $post->slug),
            'reason' => 'spam',
        ]);
    }

    public function test_report_rejects_unknown_content(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/reports', [
            'content_type' => 'post',
            'content_id' => 'missing-project',
            'reason' => 'spam',
        ])->assertNotFound();
    }

    public function test_user_cannot_report_their_own_profile(): void
    {
        $user = $this->makeEligibleUser();

        $this->actingAs($user)->postJson('/reports', [
            'content_type' => 'profile',
            'content_id' => $user->slug,
            'reason' => 'spam',
        ])->assertForbidden();

        $this->assertDatabaseCount('content_reports', 0);
    }

    public function test_report_rejects_content_the_reporter_cannot_view(): void
    {
        $reporter = $this->makeEligibleUser();
        $owner = $this->makeEligibleUser();
        $draft = Post::factory()->for($owner)->create(['visibility' => 'draft']);
        $hiddenComment = PostComment::factory()->for($reporter, 'user')->create([
            'post_id' => $draft->id,
            'post_slug' => $draft->slug,
            'user_id' => $owner->id,
            'is_hidden' => true,
            'moderation_status' => 'hidden',
        ]);

        $this->actingAs($reporter)->postJson('/reports', [
            'content_type' => 'post',
            'content_id' => $draft->slug,
            'reason' => 'spam',
        ])->assertNotFound();
        $this->actingAs($reporter)->postJson('/reports', [
            'content_type' => 'comment',
            'content_id' => (string) $hiddenComment->id,
            'reason' => 'spam',
        ])->assertNotFound();

        $this->assertDatabaseCount('content_reports', 0);
    }

    public function test_user_cannot_submit_the_system_report_reason(): void
    {
        $reporter = $this->makeEligibleUser();
        $post = Post::factory()->for($this->makeEligibleUser())->create();

        $this->actingAs($reporter)->postJson('/reports', [
            'content_type' => 'post',
            'content_id' => $post->slug,
            'reason' => 'admin_flag',
        ])->assertUnprocessable();
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
