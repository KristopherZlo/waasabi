<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_page_loads(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_project_page_loads(): void
    {
        $this->get('/projects/power-hub-night')->assertOk();
    }

    public function test_question_page_loads(): void
    {
        $this->get('/questions/read-time-metrics')->assertOk();
    }

    public function test_showcase_and_notifications_pages_load(): void
    {
        $this->get('/showcase')->assertOk();
        $this->get('/notifications')->assertOk();
    }

    public function test_login_and_register_pages_load(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_locale_change_sets_session(): void
    {
        $response = $this->from('/')->get('/locale/fi');

        $response->assertRedirect('/');
        $this->assertSame('fi', session('locale'));
    }

    public function test_invalid_locale_returns_400(): void
    {
        $this->get('/locale/xx')->assertStatus(400);
    }
}
