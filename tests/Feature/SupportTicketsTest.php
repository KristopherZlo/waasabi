<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class SupportTicketsTest extends TestCase
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

    public function test_guest_cannot_create_ticket(): void
    {
        $response = $this->postJson('/support/tickets', [
            'kind' => 'question',
            'subject' => 'Need help',
            'body' => str_repeat('Help me ', 5),
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_create_ticket(): void
    {
        $user = $this->makeEligibleUser();

        $response = $this->actingAs($user)->post('/support/tickets', [
            'kind' => 'question',
            'subject' => 'Need help',
            'body' => str_repeat('Help me ', 5),
        ]);

        $response->assertRedirect(route('support', ['tab' => 'tickets']));
        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $user->id,
            'kind' => 'question',
            'subject' => 'Need help',
            'status' => 'open',
        ]);
    }

    public function test_user_can_add_message_to_own_ticket(): void
    {
        $user = $this->makeEligibleUser();
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'kind' => 'bug',
            'subject' => 'Broken page',
            'body' => 'Something broke.',
            'status' => 'open',
            'meta' => [],
        ]);

        $response = $this->actingAs($user)->post('/support/tickets/' . $ticket->id . '/messages', [
            'message' => 'Here is more detail.',
        ]);

        $response->assertRedirect(route('support', ['tab' => 'tickets', 'ticket' => $ticket->id]));

        $ticket->refresh();
        $messages = $ticket->meta['messages'] ?? [];
        $this->assertNotEmpty($messages);
        $this->assertSame('Here is more detail.', $messages[0]['body'] ?? null);
    }

    public function test_user_cannot_message_other_users_ticket(): void
    {
        $owner = $this->makeEligibleUser();
        $other = $this->makeEligibleUser();
        $ticket = SupportTicket::create([
            'user_id' => $owner->id,
            'kind' => 'bug',
            'subject' => 'Broken page',
            'body' => 'Something broke.',
            'status' => 'open',
            'meta' => [],
        ]);

        $response = $this->actingAs($other)->post('/support/tickets/' . $ticket->id . '/messages', [
            'message' => 'Trying to reply.',
        ]);

        $response->assertStatus(403);
    }
}
