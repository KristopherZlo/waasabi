<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_page_loads(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_feed_question_preview_does_not_show_raw_markdown(): void
    {
        Post::factory()->question()->create([
            'body_markdown' => "## Preview heading\n\n[linked text](https://example.com), **bold words** & choices.",
            'body_html' => null,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Feed')
                ->where('works.data.0.preview', fn (string $preview) => str_contains($preview, 'Preview heading')
                    && str_contains($preview, 'bold words & choices')
                    && ! str_contains($preview, '##')
                    && ! str_contains($preview, '**')));
    }

    public function test_spa_request_returns_only_the_page_fragment(): void
    {
        $version = app(HandleInertiaRequests::class)->version(request());

        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
            ->get('/')
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Feed')
            ->assertJsonPath('url', '/');
    }

    public function test_feed_exposes_real_interaction_counts(): void
    {
        $post = Post::factory()->create();
        DB::table('reading_activity')->insert([
            'post_id' => $post->slug,
            'ip_hash' => hash('sha256', 'reader'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Feed')
                ->where('works.data.0.id', $post->id)
                ->where('works.data.0.comments', 0)
                ->where('works.data.0.score', 0));
    }

    public function test_project_page_loads(): void
    {
        Post::factory()->create(['slug' => 'power-hub-night', 'type' => 'post']);
        $this->get('/projects/power-hub-night')->assertOk();
    }

    public function test_project_page_shows_related_projects(): void
    {
        Post::factory()->create([
            'slug' => 'target-project',
            'type' => 'post',
            'tags' => ['hardware', 'prototype'],
        ]);
        Post::factory()->create([
            'slug' => 'related-project',
            'type' => 'post',
            'title' => 'A related hardware build',
            'tags' => ['hardware'],
        ]);

        $this->get('/projects/target-project')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Work')
                ->where('related.0.title', 'A related hardware build'));
    }

    public function test_question_page_loads(): void
    {
        Post::factory()->question()->create(['slug' => 'read-time-metrics']);
        $this->get('/questions/read-time-metrics')->assertOk();
    }

    public function test_question_answers_include_score_and_current_vote(): void
    {
        $viewer = User::factory()->create();
        $question = Post::factory()->question()->create();
        $answer = PostComment::factory()->create([
            'post_id' => $question->id,
            'post_slug' => $question->slug,
            'parent_id' => null,
            'vote_score' => -1,
        ]);
        DB::table('post_comment_votes')->insert([
            'post_comment_id' => $answer->id,
            'user_id' => $viewer->id,
            'value' => -1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewer)->get(route('questions.show', $question->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('comments.data.0.score', -1)
                ->where('comments.data.0.vote', -1));
    }

    public function test_showcase_and_notifications_pages_load(): void
    {
        $this->get('/showcase')->assertOk();
        $this->get('/notifications')->assertRedirect(route('login'));
    }

    public function test_login_and_register_pages_load(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth')->where('mode', 'login'));
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth')->where('mode', 'register'));
    }

    public function test_device_settings_page_loads(): void
    {
        $user = User::factory()->create([
            'privacy_allow_mentions' => false,
            'security_login_alerts' => true,
        ]);
        $this->get('/settings')->assertRedirect(route('login'));
        $this->actingAs($user)->get('/settings')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Settings')
            ->where('person.email', $user->email)
            ->where('person.privacy_allow_mentions', false)
            ->where('person.security_login_alerts', true));
    }

    public function test_not_found_page_contains_the_autostart_game(): void
    {
        $this->get('/definitely-missing')
            ->assertNotFound()
            ->assertSee(__('ui.errors.not_found_code'))
            ->assertSee(__('ui.errors.not_found_title'))
            ->assertSee(__('ui.errors.not_found_home'))
            ->assertSee(__('ui.errors.not_found_game_started'))
            ->assertSee('data-not-found-game', false)
            ->assertSee('images/earth.svg', false)
            ->assertSee('images/box.svg', false)
            ->assertSee('images/star.svg', false)
            ->assertDontSee('Click to start');
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
