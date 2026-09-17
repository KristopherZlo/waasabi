<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_legacy_collaboration_query_does_not_prefill_a_project_tag(): void
    {
        $this->actingAs($this->makeEligibleUser())
            ->get(route('publish', ['collaboration' => 1]))
            ->assertOk()
            ->assertDontSee('value="collaboration"', false);
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

    public function test_project_options_follow_the_current_locale(): void
    {
        app()->setLocale('fi');

        $this->actingAs($this->makeEligibleUser())
            ->get(route('publish'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Editor')
                ->where('categories.visual-art', 'Kuvataide')
                ->where('mediaTypes.mixed', 'Sekatekniikka')
                ->where('licenses.all-rights-reserved', 'Kaikki oikeudet pidätetään'));
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
        $this->assertNull($post->status);
        $response->assertRedirect(route('questions.show', $post->slug));
    }

    public function test_question_ignores_project_team_and_attachment_fields(): void
    {
        Storage::fake('public');
        $user = $this->makeEligibleUser();
        $proposedCoauthor = $this->makeEligibleUser();

        $this->actingAs($user)->post('/publish', [
            'publish_type' => 'question',
            'title' => 'Question without a project team',
            'question_body' => 'How should I choose the right format before this idea becomes a project?',
            'coauthors' => '@'.$proposedCoauthor->slug,
            'attachments' => [UploadedFile::fake()->create('draft.pdf', 100, 'application/pdf')],
        ])->assertRedirect();

        $question = Post::where('title', 'Question without a project team')->firstOrFail();
        $this->assertDatabaseMissing('project_members', ['post_id' => $question->id]);
        $this->assertDatabaseMissing('post_attachments', ['post_id' => $question->id]);
        $this->assertNull($question->status);
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

    public function test_publish_rejects_an_unbounded_project_body(): void
    {
        $user = $this->makeEligibleUser();

        $this->actingAs($user)->from('/publish')->post('/publish', [
            'publish_type' => 'post',
            'title' => 'Oversized project',
            'body' => str_repeat('x', 100001),
        ])->assertRedirect('/publish')->assertSessionHasErrors('body');

        $this->assertDatabaseMissing('posts', ['title' => 'Oversized project']);
    }

    public function test_user_can_save_server_side_draft(): void
    {
        $user = $this->makeEligibleUser();

        $response = $this->actingAs($user)->post('/publish', [
            'publish_type' => 'post',
            'publish_action' => 'draft',
            'title' => 'Unfinished album notes',
            'body' => '',
        ]);

        $post = Post::where('title', 'Unfinished album notes')->firstOrFail();
        $this->assertSame('draft', $post->visibility);
        $response->assertRedirect(route('posts.edit', $post->slug));
        $this->post(route('logout'));
        $this->get(route('project', $post->slug))->assertNotFound();
        $this->actingAs($user)->get(route('project', $post->slug))->assertOk();
    }

    public function test_project_stores_creative_metadata_and_attachment(): void
    {
        Storage::fake('public');
        $user = $this->makeEligibleUser();

        $this->actingAs($user)->post('/publish', [
            'publish_type' => 'post',
            'title' => 'Original field recording',
            'subtitle' => 'A short sound project',
            'category' => 'music',
            'media_type' => 'audio',
            'license' => 'cc-by',
            'external_url' => 'https://example.com/demo',
            'body' => 'I recorded and arranged sounds from a local workshop into a short composition.',
            'attachments' => [UploadedFile::fake()->create('recording.mp3', 800, 'audio/mpeg')],
        ])->assertRedirect();

        $post = Post::where('title', 'Original field recording')->firstOrFail();
        $this->assertSame('music', $post->category);
        $this->assertSame('audio', $post->media_type);
        $this->assertSame('cc-by', $post->license);
        $attachment = $post->attachments()->firstOrFail();
        $this->assertSame('audio', $attachment->kind);
        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_project_member_can_post_project_update(): void
    {
        $owner = $this->makeEligibleUser();
        $member = $this->makeEligibleUser();
        $post = Post::factory()->for($owner)->create();
        $post->members()->create([
            'user_id' => $member->id,
            'invited_by' => $owner->id,
            'role' => 'composer',
            'status' => 'active',
            'can_edit' => true,
            'accepted_at' => now(),
        ]);

        $this->actingAs($member)->post(route('projects.updates.store', $post->slug), [
            'title' => 'First soundtrack draft',
            'body' => 'The first complete soundtrack draft is ready, including the opening and closing themes.',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_updates', [
            'post_id' => $post->id,
            'user_id' => $member->id,
            'title' => 'First soundtrack draft',
        ]);

        $update = $post->updates()->firstOrFail();
        $otherMember = $this->makeEligibleUser();
        $post->members()->create([
            'user_id' => $otherMember->id,
            'invited_by' => $owner->id,
            'role' => 'writer',
            'status' => 'active',
            'can_edit' => true,
            'accepted_at' => now(),
        ]);
        $this->actingAs($otherMember)
            ->delete(route('projects.updates.destroy', [$post->slug, $update]))
            ->assertForbidden();
        $this->actingAs($member)
            ->delete(route('projects.updates.destroy', [$post->slug, $update]))
            ->assertRedirect();
        $this->assertDatabaseMissing('project_updates', ['id' => $update->id]);
    }

    public function test_project_member_edit_does_not_transfer_ownership_or_change_team(): void
    {
        $owner = $this->makeEligibleUser();
        $member = $this->makeEligibleUser();
        $invitee = $this->makeEligibleUser(['privacy_allow_mentions' => true]);
        $post = Post::factory()->for($owner)->create();
        ProjectMember::create([
            'post_id' => $post->id,
            'user_id' => $member->id,
            'invited_by' => $owner->id,
            'role' => 'coauthor',
            'status' => 'active',
            'can_edit' => true,
            'accepted_at' => now(),
        ]);

        $this->actingAs($member)->post('/publish', [
            'post_id' => $post->id,
            'publish_type' => 'post',
            'title' => 'Edited without taking ownership',
            'body' => 'The project member can improve the project content without changing who owns or manages the team.',
            'coauthors' => '@'.$invitee->slug,
        ])->assertRedirect();

        $this->assertSame($owner->id, $post->fresh()->user_id);
        $this->assertDatabaseHas('project_members', [
            'post_id' => $post->id,
            'user_id' => $member->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('project_members', [
            'post_id' => $post->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function test_safe_edit_clears_automatic_moderation_but_preserves_staff_action(): void
    {
        $owner = $this->makeEligibleUser();
        $moderator = $this->makeEligibleUser(['role' => 'moderator']);
        $automatic = Post::factory()->for($owner)->create([
            'moderation_status' => 'pending',
            'is_hidden' => true,
            'hidden_by' => $owner->id,
        ]);
        $staffHidden = Post::factory()->for($owner)->create([
            'moderation_status' => 'hidden',
            'is_hidden' => true,
            'hidden_by' => $moderator->id,
        ]);
        $payload = [
            'publish_type' => 'post',
            'title' => 'A safe revised project',
            'body' => 'This revision describes the original creative process and the completed result.',
        ];

        $this->actingAs($owner)->post('/publish', $payload + ['post_id' => $automatic->id])->assertRedirect();
        $this->actingAs($owner)->post('/publish', $payload + ['post_id' => $staffHidden->id])->assertRedirect();

        $automatic->refresh();
        $this->assertSame('approved', $automatic->moderation_status);
        $this->assertFalse($automatic->is_hidden);
        $this->assertNull($automatic->hidden_by);
        $this->assertSame('hidden', $staffHidden->fresh()->moderation_status);
    }
}
