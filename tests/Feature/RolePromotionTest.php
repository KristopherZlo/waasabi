<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RolePromotionTest extends TestCase
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

    public function test_user_is_promoted_to_maker_after_upvote_threshold(): void
    {
        config([
            'roles.maker_promotion' => [
                'required_posts' => 1,
                'min_upvotes' => 1,
                'percentile' => 0,
                'window_hours' => 0,
                'min_sample' => 1,
                'exclude_nsfw' => false,
                'require_visible' => false,
                'require_approved' => false,
                'type' => 'post',
                'cache_minutes' => 1,
            ],
        ]);
        Cache::flush();

        $author = $this->makeEligibleUser(['role' => 'user']);
        $voter = $this->makeEligibleUser();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'type' => 'post',
        ]);

        $this->actingAs($voter)->postJson('/posts/' . $post->slug . '/upvote')->assertOk();

        $author->refresh();
        $this->assertSame('maker', $author->roleKey());
    }
}
