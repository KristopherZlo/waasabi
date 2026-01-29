<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class CoauthorNotificationsTest extends TestCase
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

    public function test_coauthor_receives_notification_on_publish(): void
    {
        config(['moderation.text.enabled' => false]);

        $author = $this->makeEligibleUser();
        $coauthor = $this->makeEligibleUser([
            'slug' => 'coauthor-user',
            'privacy_allow_mentions' => true,
        ]);

        $body = str_repeat('This is a detailed post body. ', 20);

        $response = $this->actingAs($author)->post('/publish', [
            'publish_type' => 'post',
            'title' => 'Coauthored post',
            'subtitle' => 'Testing coauthor',
            'status' => 'done',
            'tags' => 'test, coauthor',
            'body' => $body,
            'coauthors' => '@' . $coauthor->slug,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $coauthor->id,
        ]);
    }
}
