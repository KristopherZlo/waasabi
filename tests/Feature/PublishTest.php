<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class PublishTest extends TestCase
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

    public function test_guest_cannot_access_publish(): void
    {
        $response = $this->get('/publish');

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_publish_post(): void
    {
        $user = $this->makeEligibleUser();

        $payload = [
            'publish_type' => 'post',
            'title' => 'Test Publish Post',
            'subtitle' => 'Short summary',
            'status' => 'done',
            'tags' => 'hardware, test',
            'body' => 'This is a test post body.',
        ];

        $response = $this->actingAs($user)->post('/publish', $payload);

        $post = Post::query()->where('title', 'Test Publish Post')->first();
        $this->assertNotNull($post);
        $this->assertSame('post', $post->type);
        $response->assertRedirect(route('project', $post->slug));
    }

    public function test_user_can_publish_question(): void
    {
        $user = $this->makeEligibleUser();

        $payload = [
            'publish_type' => 'question',
            'title' => 'Test Question',
            'tags' => 'ux, question',
            'question_body' => 'How do you estimate read time for a long post?',
        ];

        $response = $this->actingAs($user)->post('/publish', $payload);

        $post = Post::query()->where('title', 'Test Question')->first();
        $this->assertNotNull($post);
        $this->assertSame('question', $post->type);
        $response->assertRedirect(route('questions.show', $post->slug));
    }

    public function test_publish_validates_required_body_for_post(): void
    {
        $user = $this->makeEligibleUser();

        $response = $this->actingAs($user)->from('/publish')->post('/publish', [
            'publish_type' => 'post',
            'title' => 'Missing Body',
        ]);

        $response->assertRedirect('/publish');
        $response->assertSessionHasErrors('body');
    }
}
