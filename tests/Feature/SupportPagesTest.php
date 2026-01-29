<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Tests\TestCase;

class SupportPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_index_loads(): void
    {
        $this->get('/support')->assertOk();
    }

    public function test_support_kb_article_loads(): void
    {
        $this->get('/support/kb/roles-badges-progression')->assertOk();
    }

    public function test_support_legal_article_loads(): void
    {
        $this->get('/support/docs/terms-of-service')->assertOk();
    }

    public function test_support_ticket_new_redirects(): void
    {
        $this->get('/support/tickets/new')
            ->assertRedirect(route('login'));
    }

    public function test_support_ticket_new_redirects_for_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'created_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($user)
            ->get('/support/tickets/new')
            ->assertRedirect(route('support', ['tab' => 'new']));
    }

    public function test_support_unknown_kb_article_returns_404(): void
    {
        $this->get('/support/kb/unknown-article')->assertStatus(404);
    }
}
