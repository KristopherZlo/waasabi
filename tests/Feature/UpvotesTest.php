<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class UpvotesTest extends TestCase
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

    public function test_user_can_upvote_and_remove_upvote(): void
    {
        $author = $this->makeEligibleUser();
        $voter = $this->makeEligibleUser();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'type' => 'post',
        ]);

        $response = $this->actingAs($voter)->postJson('/posts/' . $post->slug . '/upvote');
        $response->assertOk()->assertJson(['upvoted' => true, 'count' => 1]);

        $this->assertDatabaseHas('post_upvotes', [
            'user_id' => $voter->id,
            'post_id' => $post->id,
        ]);

        $response = $this->actingAs($voter)->postJson('/posts/' . $post->slug . '/upvote');
        $response->assertOk()->assertJson(['upvoted' => false, 'count' => 0]);

        $this->assertDatabaseMissing('post_upvotes', [
            'user_id' => $voter->id,
            'post_id' => $post->id,
        ]);
    }
}
