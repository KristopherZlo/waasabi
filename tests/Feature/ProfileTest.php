<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\ProfileWallPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_show_page_loads(): void
    {
        $user = User::factory()->create(['slug' => 'jane-doe']);

        $response = $this->get("/profile/{$user->slug}");

        $response->assertOk();
    }

    public function test_profile_page_paginates_the_authors_work(): void
    {
        $user = User::factory()->create(['slug' => 'jane-doe']);
        Post::factory()->for($user)->count(2)->create();

        $this->get("/profile/{$user->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Profile')->has('works.data', 2));
    }

    public function test_profile_defaults_to_all_content_and_can_filter_works(): void
    {
        $user = User::factory()->create(['slug' => 'filtered-maker']);
        Post::factory()->for($user)->create(['title' => 'Visible project', 'is_project' => true]);
        Post::factory()->for($user)->create(['title' => 'Standalone sketch', 'is_project' => false]);
        Post::factory()->for($user)->create(['title' => 'A useful question', 'type' => 'question', 'is_project' => false]);

        $this->get(route('profile.show', $user->slug))
            ->assertInertia(fn (Assert $page) => $page->where('workFilter.kind', 'all')
                ->has('works.data', 3));

        $this->get(route('profile.show', ['slug' => $user->slug, 'kind' => 'works', 'q' => 'sketch']))
            ->assertInertia(fn (Assert $page) => $page->where('workFilter.kind', 'works')
                ->has('works.data', 1)
                ->where('works.data.0.title', 'Standalone sketch'));
    }

    public function test_profile_page_includes_hub_badges(): void
    {
        $user = User::factory()->create(['slug' => 'badge-owner']);
        $user->grantBadge('beta', ['reason' => 'Early feedback'], false);

        $this->get(route('profile.show', $user->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Profile')
                ->where('badges.0.key', 'beta')
                ->where('badges.0.label', 'Beta')
                ->where('badges.0.reason', 'Early feedback'));
    }

    public function test_profile_redirects_to_current_user_slug(): void
    {
        $user = User::factory()->create(['slug' => 'jane-doe']);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertRedirect(route('profile.show', $user->slug));
    }

    public function test_guest_cannot_access_profile_settings(): void
    {
        $response = $this->get('/profile/settings');

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_update_profile_settings_without_role_change(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->from('/profile/settings')->post('/profile/settings', [
            'name' => 'Updated Name',
            'avatar' => 'https://example.com/avatar.png',
            'bio' => 'Short bio text.',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('profile.settings').'#profile');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'role' => 'user',
        ]);
    }

    public function test_inline_profile_update_returns_to_profile(): void
    {
        $user = User::factory()->create(['slug' => 'inline-editor']);

        $this->actingAs($user)->post(route('profile.settings.update'), [
            'name' => $user->name,
            'bio' => 'Edited directly on my page.',
            'return_to_profile' => true,
        ])->assertRedirect(route('profile.show', $user->slug));

        $this->assertSame('Edited directly on my page.', $user->fresh()->bio);
    }

    public function test_partial_settings_update_preserves_omitted_profile_fields(): void
    {
        $user = User::factory()->create([
            'bio' => 'Keep this bio.',
            'skills' => 'Illustration',
        ]);

        $this->actingAs($user)->post(route('profile.settings.update'), [
            'name' => $user->name,
            'section' => 'privacy',
            'privacy_allow_mentions' => '0',
        ])->assertRedirect(route('profile.settings').'#privacy');

        $user->refresh();
        $this->assertSame('Keep this bio.', $user->bio);
        $this->assertSame('Illustration', $user->skills);
    }

    public function test_admin_cannot_change_own_role_from_profile_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->from('/profile/settings')->post('/profile/settings', [
            'name' => 'Admin Updated',
            'avatar' => 'https://example.com/avatar.png',
            'bio' => 'Admin bio.',
            'role' => 'maker',
        ]);

        $response->assertRedirect(route('profile.settings').'#profile');
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
        ]);
    }

    public function test_profile_accepts_a_valid_small_compressed_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.settings.update'), [
            'name' => $user->name,
            'bio' => 'Updated profile image.',
            'avatar_file' => UploadedFile::fake()->image('avatar.jpg', 512, 512),
        ])->assertSessionHasNoErrors();

        $avatar = (string) $user->fresh()->avatar;
        $this->assertStringStartsWith('storage/uploads/avatars/', $avatar);
        Storage::disk('public')->assertExists(str_replace('storage/', '', $avatar));
        $this->assertDatabaseHas('content_reports', [
            'content_type' => 'content',
            'content_id' => $avatar,
            'resolved_status' => 'pending',
        ]);
    }

    public function test_user_can_follow_and_unfollow(): void
    {
        $follower = User::factory()->create(['slug' => 'follower-user']);
        $target = User::factory()->create(['slug' => 'target-user']);

        $response = $this->actingAs($follower)->post("/profile/{$target->slug}/follow");
        $response->assertRedirect();
        $this->assertDatabaseHas('user_follows', [
            'follower_id' => $follower->id,
            'following_id' => $target->id,
        ]);

        $response = $this->actingAs($follower)->post("/profile/{$target->slug}/follow");
        $response->assertRedirect();
        $this->assertDatabaseMissing('user_follows', [
            'follower_id' => $follower->id,
            'following_id' => $target->id,
        ]);
    }

    public function test_user_cannot_follow_self(): void
    {
        $user = User::factory()->create(['slug' => 'self-user']);

        $response = $this->actingAs($user)->post("/profile/{$user->slug}/follow");

        $response->assertRedirect();
        $this->assertDatabaseMissing('user_follows', [
            'follower_id' => $user->id,
            'following_id' => $user->id,
        ]);
    }

    public function test_read_later_requires_auth(): void
    {
        $this->get('/read-later')->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get('/read-later')->assertOk();
    }

    public function test_read_later_list_hides_a_saved_draft(): void
    {
        $user = User::factory()->create();
        $draft = Post::factory()->create(['visibility' => 'draft']);
        DB::table('post_saves')->insert([
            'user_id' => $user->id,
            'post_id' => $draft->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->getJson(route('read-later.list'))
            ->assertOk()
            ->assertJsonMissing(['slug' => $draft->slug]);
    }

    public function test_public_profile_hides_comments_on_an_inaccessible_draft(): void
    {
        $commenter = User::factory()->create();
        $draft = Post::factory()->create(['visibility' => 'draft']);
        PostComment::factory()->for($commenter, 'user')->create([
            'post_id' => $draft->id,
            'post_slug' => $draft->slug,
            'body' => 'Private draft discussion',
        ]);

        $this->get(route('profile.show', $commenter->slug))
            ->assertOk()
            ->assertDontSee('Private draft discussion');
    }

    public function test_profile_showcase_readme_and_stats_are_public(): void
    {
        $owner = User::factory()->create(['slug' => 'showcase-owner']);
        $follower = User::factory()->create();
        $project = Post::factory()->for($owner)->create(['is_project' => true, 'visibility' => 'public', 'moderation_status' => 'approved']);
        DB::table('user_follows')->insert(['follower_id' => $follower->id, 'following_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('post_upvotes')->insert(['user_id' => $follower->id, 'post_id' => $project->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($owner)->post(route('profile.settings.update'), [
            'name' => $owner->name,
            'profile_readme' => '## Things I make',
            'showcase_project_ids' => [$project->id],
            'showcase_project_ids_present' => '1',
        ])->assertSessionHasNoErrors();

        $this->get(route('profile.show', $owner->slug))->assertInertia(fn (Assert $page) => $page
            ->where('stats.followers', 1)->where('stats.upvotes', 1)
            ->where('showcase.0.id', $project->id)
            ->where('profileReadmeHtml', fn ($html) => str_contains($html, 'Things I make')));
    }

    public function test_profile_showcase_accepts_a_standalone_work(): void
    {
        $owner = User::factory()->create();
        $work = Post::factory()->for($owner)->create(['type' => 'post', 'is_project' => false]);

        $this->actingAs($owner)->post(route('profile.settings.update'), [
            'name' => $owner->name,
            'showcase_project_ids' => [$work->id],
            'showcase_project_ids_present' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('profile_showcase_projects', ['user_id' => $owner->id, 'post_id' => $work->id]);
    }

    public function test_profile_text_removes_interface_direction_controls(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('profile.settings.update'), ['name' => "Safe\u{202E} name"])
            ->assertSessionHasNoErrors();

        $this->assertSame('Safe name', $owner->fresh()->name);
    }

    public function test_profile_showcase_can_use_a_public_github_readme(): void
    {
        $owner = User::factory()->create(['slug' => 'github-readme-owner']);
        Http::fake([
            'api.github.com/repos/octocat/hello-world/readme' => Http::response('# README from GitHub'),
        ]);

        $this->actingAs($owner)->post(route('profile.settings.update'), [
            'name' => $owner->name,
            'profile_readme' => '# Local fallback',
            'github_readme_repository' => 'https://github.com/octocat/hello-world',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'github_readme_repository' => 'https://github.com/octocat/hello-world']);
        $this->get(route('profile.show', $owner->slug))->assertInertia(fn (Assert $page) => $page
            ->where('profileReadmeHtml', fn ($html) => str_contains($html, 'README from GitHub') && ! str_contains($html, 'Local fallback'))
            ->where('profileReadmeSource.repository', 'octocat/hello-world')
            ->where('profileReadmeSource.url', 'https://github.com/octocat/hello-world'));

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/octocat/hello-world/readme'
            && $request->hasHeader('Accept', 'application/vnd.github.raw+json'));
    }

    public function test_wall_respects_owner_mode_and_owner_can_delete_any_entry(): void
    {
        $owner = User::factory()->create(['slug' => 'wall-owner', 'wall_mode' => 'owner']);
        $visitor = User::factory()->create();
        $body = 'I enjoyed the project and would like to see the next update.';

        $this->actingAs($visitor)->post(route('profile.wall.store', $owner->slug), ['body' => $body])->assertForbidden();
        $owner->update(['wall_mode' => 'everyone']);
        $this->actingAs($visitor)->post(route('profile.wall.store', $owner->slug), ['body' => $body])->assertRedirect();
        $visitorPost = ProfileWallPost::firstOrFail();
        $this->actingAs($owner)->patch(route('profile.wall.update', $visitorPost), ['body' => 'Owner cannot rewrite a visitor post.'])->assertForbidden();
        $this->actingAs($visitor)->patch(route('profile.wall.update', $visitorPost), ['body' => 'Updated after spotting a typo in the original wall post.'])->assertRedirect();
        $this->assertDatabaseHas('profile_wall_posts', ['id' => $visitorPost->id, 'body' => 'Updated after spotting a typo in the original wall post.']);
        $this->actingAs($owner)->post(route('profile.wall.store', $owner->slug), ['body' => 'A quick update from My work.', 'return_view' => 'work'])
            ->assertRedirect(route('profile.show', ['slug' => $owner->slug, 'view' => 'work']));
        $this->actingAs($owner)->delete(route('profile.wall.destroy', $visitorPost))->assertRedirect();
        $this->assertDatabaseMissing('profile_wall_posts', ['id' => $visitorPost->id]);
    }
}
