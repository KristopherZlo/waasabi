<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Services\FeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Cache::flush();
    }

    public function test_unknown_demo_routes_no_longer_render_fake_content(): void
    {
        $this->get('/projects/power-hub-night')->assertNotFound();
        $this->get('/questions/read-time-metrics')->assertNotFound();
    }

    public function test_feed_filters_real_projects_by_included_and_excluded_tags(): void
    {
        Post::factory()->create(['title' => 'Painted wall', 'tags' => ['graffiti', 'outdoor']]);
        Post::factory()->create(['title' => 'Indoor sketch', 'tags' => ['drawing', 'indoor']]);
        Post::factory()->create(['title' => 'Outdoor sketch', 'tags' => ['drawing', 'outdoor']]);

        $this->get('/?tags=graffiti')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Feed')->has('works.data', 1)->where('works.data.0.title', 'Painted wall'));
        $this->get('/?exclude=graffiti')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Feed')->has('works.data', 2));
        $this->get('/?tags=drawing&exclude=indoor')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Feed')->has('works.data', 1)->where('works.data.0.title', 'Outdoor sketch'));
    }

    public function test_feed_is_hot_by_default_and_can_be_sorted_by_newest(): void
    {
        $popular = Post::factory()->create(['title' => 'Popular work', 'activity_at' => now()->subDay()]);
        $newest = Post::factory()->create(['title' => 'Newest work', 'activity_at' => now()]);
        $voters = User::factory()->count(2)->create();
        foreach ($voters as $voter) {
            DB::table('post_upvotes')->insert(['post_id' => $popular->id, 'user_id' => $voter->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('sort', 'hot')->where('works.data.0.id', $popular->id));
        $this->get('/?sort=new')->assertInertia(fn (Assert $page) => $page
            ->where('sort', 'new')->where('works.data.0.id', $newest->id));
    }

    public function test_search_uses_database_and_hides_drafts(): void
    {
        $author = User::factory()->create(['name' => 'Sound Builder']);
        Post::factory()->for($author)->create(['title' => 'Modular sound garden', 'visibility' => 'public']);
        Post::factory()->for($author)->create(['title' => 'Secret modular notes', 'visibility' => 'draft']);

        $this->getJson('/search?q=modular')->assertOk()
            ->assertJsonFragment(['title' => 'Modular sound garden'])
            ->assertJsonMissing(['title' => 'Secret modular notes']);
    }

    public function test_draft_is_visible_only_to_owner_and_not_on_public_profile(): void
    {
        $owner = User::factory()->create();
        $draft = Post::factory()->for($owner)->create(['visibility' => 'draft']);

        $this->get(route('project', $draft->slug))->assertNotFound();
        $this->actingAs($owner)->get(route('project', $draft->slug))->assertOk();
        $this->post('/logout');
        $this->get(route('profile.show', $owner->slug))->assertDontSee($draft->title);
        $this->actingAs($owner)->get(route('profile.show', $owner->slug))->assertSee($draft->title);
    }

    public function test_moderator_can_inspect_hidden_project_but_public_cannot(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);
        $post = Post::factory()->create([
            'is_hidden' => true,
            'moderation_status' => 'pending',
        ]);

        $this->get(route('project', $post->slug))->assertNotFound();
        $this->actingAs($moderator)->get(route('project', $post->slug))->assertOk();

        $chunk = FeedService::buildFeedChunk('projects', 0, 1, $moderator);
        $this->assertSame(1, $chunk['total']);
        $this->assertFalse($chunk['has_more']);
    }

    public function test_public_comment_count_excludes_banned_authors(): void
    {
        $post = Post::factory()->create();
        $bannedAuthor = User::factory()->create(['is_banned' => true]);
        PostComment::factory()->for($bannedAuthor, 'user')->create([
            'post_id' => $post->id,
            'post_slug' => $post->slug,
        ]);

        $stats = FeedService::preparePostStats([$post], null);

        $this->assertSame(0, $stats['comments'][$post->slug] ?? 0);
    }

    public function test_banned_author_content_is_not_viewable_by_a_project_member(): void
    {
        $owner = User::factory()->create(['is_banned' => true]);
        $member = User::factory()->create();
        $post = Post::factory()->for($owner)->create();
        $post->members()->create([
            'user_id' => $member->id,
            'invited_by' => $owner->id,
            'status' => 'active',
            'can_edit' => true,
        ]);

        $this->actingAs($member)->get(route('project', $post->slug))->assertNotFound();
    }
}
