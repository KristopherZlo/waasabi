<?php

namespace Tests\Feature;

use App\Models\CollaborationRequest;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\UploadAsset;
use App\Models\User;
use App\Services\FeedService;
use App\Services\UploadAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WaasabiTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_help_without_project_can_be_joined_and_left(): void
    {
        $owner = User::factory()->create();
        $helper = User::factory()->create();
        $this->actingAs($owner)->get(route('collaboration.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('HelpEditor'));
        $this->post(route('collaboration.store'), ['role' => '3d-artist', 'summary' => 'A little lamp for my game.'])->assertSessionHasNoErrors();
        $opening = CollaborationRequest::firstOrFail();
        $this->assertNull($opening->post_id);
        $this->get(route('collaboration.show', $opening))->assertOk();
        $this->get(route('feed', ['stream' => 'collaboration']))->assertOk()->assertSee($opening->title);
        $this->actingAs($helper)->post(route('collaboration.applications.store', $opening), [])->assertSessionHasNoErrors();
        $application = $opening->applications()->firstOrFail();
        $this->actingAs($owner)->patch(route('collaboration.applications.decide', $application), ['status' => 'accepted'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $application->fresh()->status);
        $this->actingAs($helper)->delete(route('collaboration.applications.withdraw', $application))->assertRedirect();
        $this->assertSame('withdrawn', $application->fresh()->status);
        $this->post(route('collaboration.applications.store', $opening), [])->assertSessionHasNoErrors();
        $this->assertSame('pending', $application->fresh()->status);
    }

    public function test_editor_permission_is_separate_and_only_the_owner_can_grant_it(): void
    {
        $owner = User::factory()->create();
        $helper = User::factory()->create();
        $post = Post::factory()->for($owner)->create();
        $member = $post->members()->create(['user_id' => $helper->id, 'invited_by' => $owner->id, 'role' => '3d-artist', 'status' => 'active', 'accepted_at' => now(), 'can_edit' => false]);
        $this->assertFalse($helper->can('update', $post));
        $this->actingAs($helper)->patch(route('project-members.permissions', [$post, $member]), ['can_edit' => true])->assertForbidden();
        $this->actingAs($owner)->patch(route('project-members.permissions', [$post, $member]), ['can_edit' => true])->assertRedirect();
        $this->assertTrue($helper->can('update', $post));
        $this->actingAs($helper)->delete(route('project-members.destroy', [$post, $member]))->assertRedirect();
        $this->assertFalse($helper->can('update', $post));
        $this->get(route('profile.show', $helper->slug))->assertOk()->assertSee($post->title);
    }

    public function test_following_a_project_delivers_updates_but_never_private_content(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $post = Post::factory()->for($owner)->create();
        $this->actingAs($reader)->put(route('projects.follow', $post), ['following' => true])->assertRedirect();
        $this->put(route('projects.follow', $post), ['following' => true])->assertRedirect();
        $this->assertSame(1, $post->followers()->count());
        $this->actingAs($owner)->get(route('projects.updates.create', $post->slug))->assertOk();
        $this->post(route('projects.updates.store', $post->slug), ['title' => 'A first sketch', 'body' => '## The next step'."\n\n".'Here is what I tried today.'])->assertSessionHasNoErrors();
        $update = $post->updates()->firstOrFail();
        $this->assertDatabaseHas('user_notifications', ['user_id' => $reader->id, 'link' => route('project', $post->slug).'#update-'.$update->id]);
        $this->assertNotNull($post->fresh()->activity_at);
        $this->get(route('projects.updates.edit', [$post->slug, $update]))->assertOk();
        $this->put(route('projects.updates.update', [$post->slug, $update]), ['title' => 'A second sketch', 'body' => 'A revised drawing for the project.'])->assertSessionHasNoErrors();
        $this->assertSame('A second sketch', $update->fresh()->title);
        $count = $reader->notifications()->count();
        $post->update(['visibility' => 'draft']);
        $this->post(route('projects.updates.store', $post->slug), ['title' => 'Private sketch', 'body' => 'Not ready to share this drawing yet.'])->assertSessionHasNoErrors();
        $this->assertSame($count, $reader->notifications()->count());
        $this->actingAs($reader)->get(route('project', $post->slug))->assertNotFound();
        $this->get(route('projects.updates.edit', [$post->slug, $update]))->assertForbidden();
    }

    public function test_journal_uploads_survive_a_later_project_edit_and_staff_can_hide_an_update(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->create();
        $path = 'storage/uploads/editor/drawing.webp';
        Storage::disk('public')->put('uploads/editor/drawing.webp', 'sample');
        $asset = UploadAsset::create(['user_id' => $owner->id, 'kind' => 'editor', 'path' => $path]);
        $this->actingAs($owner)->post(route('projects.updates.store', $post->slug), ['title' => 'A drawing', 'body' => '![Drawing](/'.$path.')'])->assertSessionHasNoErrors();
        $this->assertSame($post->id, $asset->fresh()->post_id);
        app(UploadAssetService::class)->syncEditorAssets($post, $owner, 'A new project description.');
        Storage::disk('public')->assertExists('uploads/editor/drawing.webp');
        $update = $post->updates()->firstOrFail();
        $moderator = User::factory()->create(['role' => 'moderator']);
        $this->actingAs($moderator)->patch(route('journal.moderate', $update), ['is_hidden' => true, 'reason' => 'Image needs review'])->assertRedirect();
        $reader = User::factory()->create();
        $this->actingAs($reader)->get(route('project', $post->slug))->assertOk()->assertDontSee('id="update-'.$update->id.'"', false);
        $this->actingAs($owner)->put(route('projects.updates.update', [$post->slug, $update]), ['title' => 'A drawing', 'body' => 'An edited caption for the drawing.'])->assertSessionHasNoErrors();
        $this->assertTrue($update->fresh()->is_hidden);
        $this->actingAs($moderator)->get(route('journal.moderation'))->assertOk()->assertSee('A drawing');
        $this->patch(route('journal.moderate', $update), ['is_hidden' => false, 'reason' => 'Reviewed'])->assertRedirect();
        $this->assertFalse($update->fresh()->is_hidden);
    }

    public function test_following_and_unanswered_discovery_are_filtered_on_the_server(): void
    {
        $reader = User::factory()->create();
        $followed = Post::factory()->create(['title' => 'Following this project']);
        $other = Post::factory()->create(['title' => 'An unrelated project']);
        $followed->followers()->attach($reader);
        $this->actingAs($reader)->get(route('feed', ['filter' => 'following']))->assertOk();
        $feed = FeedService::buildFeedChunk('projects', 0, 20, $reader, 'following');
        $this->assertSame([$followed->id], collect($feed['items'])->pluck('data.id')->all());
        PostComment::factory()->create(['post_id' => $followed->id, 'post_slug' => $followed->slug]);
        $feed = FeedService::buildFeedChunk('projects', 0, 20, $reader, 'quiet');
        $this->assertSame([$other->id], collect($feed['items'])->pluck('data.id')->all());
    }

    public function test_people_directory_and_profile_do_not_expose_hidden_featured_projects(): void
    {
        $user = User::factory()->create(['open_to_help' => true, 'skills' => 'Blender']);
        $hidden = Post::factory()->for($user)->create(['title' => 'Private portfolio piece', 'visibility' => 'draft']);
        $user->update(['featured_post_id' => $hidden->id]);
        $this->get(route('people', ['q' => 'Blender']))->assertOk()->assertSee($user->name);
        $this->get(route('profile.show', $user->slug))->assertOk()->assertDontSee($hidden->title);
        $this->actingAs($user)->get(route('profile.settings'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Settings')->where('person.skills', 'Blender'));
        $otherPost = Post::factory()->for(User::factory())->create();
        $this->post(route('profile.settings.update'), ['name' => $user->name, 'featured_post_id' => $otherPost->id])->assertSessionHasErrors('featured_post_id');
    }

    public function test_comment_pagination_includes_replies_and_excludes_hidden_comments(): void
    {
        $post = Post::factory()->create();
        $parent = PostComment::factory()->create(['post_id' => $post->id, 'post_slug' => $post->slug, 'body' => 'A visible comment']);
        PostComment::factory()->create(['post_id' => $post->id, 'post_slug' => $post->slug, 'parent_id' => $parent->id, 'body' => 'A visible reply']);
        PostComment::factory()->create(['post_id' => $post->id, 'post_slug' => $post->slug, 'is_hidden' => true, 'body' => 'Hidden comment']);
        $this->getJson(route('project.comments.chunk', $post->slug))->assertOk()->assertJsonPath('total', 1)
            ->assertSee('A visible reply')->assertDontSee('Hidden comment');
    }
}
