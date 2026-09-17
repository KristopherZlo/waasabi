<?php

namespace Tests\Feature;

use App\Models\CollaborationApplication;
use App\Models\CollaborationComment;
use App\Models\CollaborationRequest;
use App\Models\Post;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CollaborationTest extends TestCase
{
    use RefreshDatabase;

    public function test_collaboration_request_belongs_to_a_real_project(): void
    {
        $owner = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create();

        $response = $this->actingAs($owner)->post(route('collaboration.store'), [
            'post_id' => $project->id,
            'title' => 'Looking for an illustrator',
            'role' => 'illustrator',
            'availability' => 'part-time',
            'format' => 'remote',
            'skills' => 'character art, ink, composition',
            'summary' => $this->goodText('We are preparing a short illustrated story and need help designing the main characters and composing six finished scenes.'),
            'expires_in_days' => 30,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('collaboration_requests', [
            'post_id' => $project->id,
            'user_id' => $owner->id,
            'role' => 'illustrator',
            'status' => 'open',
        ]);
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_user_cannot_create_request_for_someone_elses_project(): void
    {
        $owner = $this->eligibleUser();
        $stranger = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create();

        $this->actingAs($stranger)->post(route('collaboration.store'), [
            'post_id' => $project->id,
            'title' => 'Looking for a musician',
            'role' => 'musician',
            'availability' => 'flexible',
            'format' => 'remote',
            'summary' => $this->goodText(),
        ])->assertForbidden();
    }

    public function test_collaboration_requires_a_public_approved_project(): void
    {
        $owner = $this->eligibleUser();
        $candidate = $this->eligibleUser();
        $draft = Post::factory()->for($owner)->create(['visibility' => 'draft']);

        $this->actingAs($owner)->post(route('collaboration.store'), [
            'post_id' => $draft->id,
            'title' => 'Private request',
            'role' => 'illustrator',
            'availability' => 'flexible',
            'format' => 'remote',
            'summary' => $this->goodText(),
        ])->assertSessionHasErrors('post_id');

        $legacyRequest = $this->collaborationRequest($owner, $draft);
        $this->get(route('feed', ['stream' => 'collaboration']))->assertDontSee($legacyRequest->title);
        $this->get(route('collaboration.show', $legacyRequest))->assertNotFound();
        $this->actingAs($candidate)->post(route('collaboration.applications.store', $legacyRequest), [
            'message' => $this->goodText(),
        ])->assertNotFound();

        $this->assertDatabaseCount('collaboration_applications', 0);
    }

    public function test_project_editor_cannot_manage_the_owner_collaboration_requests(): void
    {
        $owner = $this->eligibleUser();
        $editor = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create();
        ProjectMember::create([
            'post_id' => $project->id,
            'user_id' => $editor->id,
            'invited_by' => $owner->id,
            'role' => 'developer',
            'status' => 'active',
            'can_edit' => true,
            'accepted_at' => now(),
        ]);
        $collaborationRequest = $this->collaborationRequest($owner, $project);

        $this->actingAs($editor)->post(route('collaboration.store'), [
            'post_id' => $project->id,
            'title' => 'Trying to add another role',
            'role' => 'musician',
            'availability' => 'flexible',
            'format' => 'remote',
            'summary' => $this->goodText(),
        ])->assertForbidden();
        $this->actingAs($editor)
            ->patch(route('collaboration.status', $collaborationRequest), ['status' => 'closed'])
            ->assertForbidden();
    }

    public function test_member_can_leave_but_cannot_remove_another_member(): void
    {
        $owner = $this->eligibleUser();
        $member = $this->eligibleUser();
        $otherMember = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create();
        $membership = ProjectMember::create([
            'post_id' => $project->id,
            'user_id' => $member->id,
            'invited_by' => $owner->id,
            'role' => 'illustrator',
            'status' => 'active',
            'can_edit' => true,
            'accepted_at' => now(),
        ]);
        $otherMembership = ProjectMember::create([
            'post_id' => $project->id,
            'user_id' => $otherMember->id,
            'invited_by' => $owner->id,
            'role' => 'writer',
            'status' => 'active',
            'can_edit' => true,
            'accepted_at' => now(),
        ]);

        $this->actingAs($member)
            ->delete(route('project-members.destroy', [$project, $otherMembership]))
            ->assertForbidden();
        $this->actingAs($member)
            ->delete(route('project-members.destroy', [$project, $membership]))
            ->assertRedirect();

        $this->assertSame('active', $otherMembership->fresh()->status);
        $this->assertSame('removed', $membership->fresh()->status);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $owner->id,
            'text' => __('ui.notifications.member_left', ['user' => $member->name, 'title' => $project->title]),
        ]);
    }

    public function test_candidate_can_apply_and_owner_can_accept(): void
    {
        $owner = $this->eligibleUser();
        $candidate = $this->eligibleUser();
        $otherCandidate = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create();
        $request = $this->collaborationRequest($owner, $project);

        $this->actingAs($candidate)->post(route('collaboration.applications.store', $request), [
            'message' => $this->goodText('I have illustrated two small books and can share a first character sketch this week.'),
        ])->assertRedirect();
        $this->actingAs($otherCandidate)->post(route('collaboration.applications.store', $request), [
            'message' => $this->goodText('I work with ink and digital color and would like to help with these scenes.'),
        ])->assertRedirect();

        $application = CollaborationApplication::where('user_id', $candidate->id)->firstOrFail();
        $this->actingAs($owner)->patch(route('collaboration.applications.decide', $application), [
            'status' => 'accepted',
        ])->assertRedirect();

        $this->assertDatabaseHas('project_members', [
            'post_id' => $project->id,
            'user_id' => $candidate->id,
            'status' => 'active',
            'can_edit' => false,
        ]);
        $this->assertDatabaseHas('collaboration_requests', ['id' => $request->id, 'status' => 'open']);
        $this->assertDatabaseHas('collaboration_applications', [
            'collaboration_request_id' => $request->id,
            'user_id' => $otherCandidate->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $candidate->id,
            'text' => __('ui.notifications.application_accepted', ['title' => $request->title]),
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $otherCandidate->id,
            'text' => __('ui.notifications.application_rejected', ['title' => $request->title]),
        ]);
        $this->assertFalse($candidate->can('update', $project));
    }

    public function test_accepted_application_can_link_two_projects(): void
    {
        $owner = $this->eligibleUser();
        $candidate = $this->eligibleUser();
        $targetProject = Post::factory()->for($owner)->create(['title' => 'Animated short film']);
        $partnerProject = Post::factory()->for($candidate)->create(['title' => 'Original soundtrack album']);
        $request = $this->collaborationRequest($owner, $targetProject);

        $this->actingAs($candidate)->post(route('collaboration.applications.store', $request), [
            'applicant_post_id' => $partnerProject->id,
            'message' => $this->goodText('Our soundtrack project can contribute an original score and sound design to the film.'),
        ])->assertRedirect();

        $application = CollaborationApplication::where('user_id', $candidate->id)->firstOrFail();
        $this->assertSame($partnerProject->id, $application->applicant_post_id);
        $this->actingAs($owner)->patch(route('collaboration.applications.decide', $application), [
            'status' => 'accepted',
        ])->assertRedirect();

        $this->get(route('project', $targetProject->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Work')->where('partners.0.title', $partnerProject->title));
        $this->get(route('project', $partnerProject->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Work')->where('partners.0.title', $targetProject->title));
    }

    public function test_application_project_must_be_a_different_public_project_owned_by_candidate(): void
    {
        $owner = $this->eligibleUser();
        $candidate = $this->eligibleUser();
        $other = $this->eligibleUser();
        $targetProject = Post::factory()->for($owner)->create();
        $draftProject = Post::factory()->for($candidate)->create(['visibility' => 'draft']);
        $otherProject = Post::factory()->for($other)->create();
        $request = $this->collaborationRequest($owner, $targetProject);
        $payload = ['message' => $this->goodText()];

        $this->actingAs($candidate)->post(route('collaboration.applications.store', $request), $payload + [
            'applicant_post_id' => $targetProject->id,
        ])->assertSessionHasErrors('applicant_post_id');
        $this->actingAs($candidate)->post(route('collaboration.applications.store', $request), $payload + [
            'applicant_post_id' => $draftProject->id,
        ])->assertSessionHasErrors('applicant_post_id');
        $this->actingAs($candidate)->post(route('collaboration.applications.store', $request), $payload + [
            'applicant_post_id' => $otherProject->id,
        ])->assertSessionHasErrors('applicant_post_id');

        $this->assertDatabaseCount('collaboration_applications', 0);
    }

    public function test_candidate_cannot_apply_twice_or_to_own_request(): void
    {
        $owner = $this->eligibleUser();
        $candidate = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create();
        $request = $this->collaborationRequest($owner, $project);
        $payload = ['message' => $this->goodText()];

        $this->actingAs($candidate)->post(route('collaboration.applications.store', $request), $payload)->assertRedirect();
        $this->actingAs($candidate)->from(route('feed', ['stream' => 'collaboration']))->post(route('collaboration.applications.store', $request), $payload)
            ->assertSessionHasErrors('message');
        $this->actingAs($owner)->post(route('collaboration.applications.store', $request), $payload)->assertForbidden();
        $this->assertDatabaseCount('collaboration_applications', 1);
    }

    public function test_candidate_can_apply_again_after_withdrawing(): void
    {
        $owner = $this->eligibleUser();
        $candidate = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create();
        $request = $this->collaborationRequest($owner, $project);
        $payload = ['message' => $this->goodText()];

        $this->actingAs($candidate)->post(route('collaboration.applications.store', $request), $payload)->assertRedirect();
        $application = CollaborationApplication::where('user_id', $candidate->id)->firstOrFail();
        $this->actingAs($candidate)->delete(route('collaboration.applications.withdraw', $application))->assertRedirect();
        $this->actingAs($candidate)->post(route('collaboration.applications.store', $request), [
            'message' => $this->goodText('I am available again and can now commit to the agreed project schedule.'),
        ])->assertRedirect();

        $this->assertSame('pending', $application->fresh()->status);
        $this->assertNull($application->fresh()->decided_at);
        $this->assertDatabaseCount('collaboration_applications', 1);
    }

    public function test_request_owner_can_close_and_reopen_request(): void
    {
        $owner = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create();
        $request = $this->collaborationRequest($owner, $project);

        $candidate = $this->eligibleUser();
        $this->actingAs($candidate)->post(route('collaboration.applications.store', $request), [
            'message' => $this->goodText(),
        ])->assertRedirect();

        $this->actingAs($owner)->patch(route('collaboration.status', $request), ['status' => 'closed'])->assertRedirect();
        $this->assertDatabaseHas('collaboration_requests', ['id' => $request->id, 'status' => 'closed']);
        $this->assertDatabaseHas('collaboration_applications', [
            'collaboration_request_id' => $request->id,
            'user_id' => $candidate->id,
            'status' => 'closed',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $candidate->id,
            'text' => __('ui.notifications.application_closed', ['title' => $request->title]),
        ]);

        $this->actingAs($owner)->patch(route('collaboration.status', $request), ['status' => 'open'])->assertRedirect();
        $this->assertDatabaseHas('collaboration_requests', ['id' => $request->id, 'status' => 'open']);
        $this->actingAs($candidate)->post(route('collaboration.applications.store', $request), [
            'message' => $this->goodText('The request is open again and I remain available to contribute to this project.'),
        ])->assertRedirect();
        $this->assertDatabaseHas('collaboration_applications', [
            'collaboration_request_id' => $request->id,
            'user_id' => $candidate->id,
            'status' => 'pending',
        ]);
    }

    public function test_collaboration_page_uses_server_side_filters(): void
    {
        $owner = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create();
        $match = $this->collaborationRequest($owner, $project, ['role' => 'illustrator', 'title' => 'Draw a short comic']);
        $this->collaborationRequest($owner, $project, ['role' => 'musician', 'title' => 'Compose a theme']);
        $secondMatch = $this->collaborationRequest($owner, $project, ['role' => 'developer', 'title' => 'Build an interactive prototype']);

        $this->get(route('feed', ['stream' => 'collaboration', 'role' => 'illustrator']))
            ->assertOk()
            ->assertSee($match->title)
            ->assertDontSee('Compose a theme');

        $this->get(route('collaboration', ['role' => 'illustrator,developer']))
            ->assertOk()
            ->assertSee($match->title)
            ->assertSee($secondMatch->title)
            ->assertDontSee('Compose a theme');
    }

    public function test_collaboration_search_expands_plain_language_synonyms(): void
    {
        $owner = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create();
        $match = $this->collaborationRequest($owner, $project, ['role' => 'developer', 'title' => 'Help with the prototype']);
        $this->collaborationRequest($owner, $project, ['role' => 'musician', 'title' => 'Compose a theme']);

        $this->get(route('collaboration', ['q' => 'code']))
            ->assertOk()
            ->assertSee($match->title)
            ->assertDontSee('Compose a theme');
    }

    public function test_collaboration_is_the_third_feed_stream(): void
    {
        $this->get(route('feed', ['stream' => 'collaboration']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Feed')->where('stream', 'collaboration'));

        $this->get(route('collaboration'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Collaborations')->has('openings.data'));

        $this->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Feed'));
    }

    public function test_collaboration_form_is_a_separate_authenticated_page(): void
    {
        $this->get(route('collaboration.create'))->assertRedirect(route('login'));

        $owner = $this->eligibleUser();
        Post::factory()->for($owner)->create(['title' => 'Public project context']);

        $this->actingAs($owner)
            ->get(route('collaboration.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('HelpEditor')
                ->where('projects.0.title', 'Public project context'));
    }

    public function test_owner_can_edit_a_collaboration_request(): void
    {
        $owner = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create();
        $opening = $this->collaborationRequest($owner, $project);

        $this->actingAs($owner)->get(route('collaboration.edit', $opening))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('HelpEditor')->where('opening.id', $opening->id));
        $this->actingAs($owner)->patch(route('collaboration.update', $opening), [
            'post_id' => $project->id, 'title' => 'Updated collaboration request', 'role' => 'developer',
            'availability' => 'part-time', 'format' => 'remote', 'skills' => 'Laravel, React',
            'summary' => $this->goodText('The updated request now has a clearer scope and ownership.'), 'expires_in_days' => 30,
        ])->assertRedirect(route('collaboration.show', $opening));
        $this->assertDatabaseHas('collaboration_requests', ['id' => $opening->id, 'title' => 'Updated collaboration request', 'role' => 'developer']);
    }

    public function test_collaboration_stream_uses_collaboration_language(): void
    {
        $owner = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create(['title' => 'Project context']);
        $opening = $this->collaborationRequest($owner, $project, ['title' => 'UI designer for mobile flows']);

        $this->get(route('feed', ['stream' => 'collaboration']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Feed')
                ->where('openings.0.title', $opening->title)
                ->where('openings.0.project.title', 'Project context'));
    }

    public function test_collaboration_has_a_dedicated_page_with_a_response_form(): void
    {
        $owner = $this->eligibleUser();
        $candidate = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create(['title' => 'Independent film']);
        $opening = $this->collaborationRequest($owner, $project, ['title' => 'Sound designer for a short film']);

        $this->get(route('collaboration.show', $opening))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Collaboration')
                ->where('opening.title', $opening->title)
                ->where('candidateProjects', []));

        $this->actingAs($candidate)
            ->get(route('collaboration.show', $opening))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Collaboration')
                ->where('opening.id', $opening->id)
                ->where('isOwner', false));
    }

    public function test_application_participants_can_use_a_private_thread(): void
    {
        $owner = $this->eligibleUser();
        $candidate = $this->eligibleUser();
        $stranger = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create();
        $opening = $this->collaborationRequest($owner, $project);

        $this->actingAs($candidate)->post(route('collaboration.applications.store', $opening), [
            'message' => $this->goodText('I would like to help and can start this week.'),
        ])->assertRedirect();
        $application = CollaborationApplication::query()->firstOrFail();

        $this->actingAs($candidate)->post(route('collaboration.applications.messages.store', $application), [
            'body' => 'Could we start with a short planning call next Tuesday?',
        ])->assertRedirect();
        $this->actingAs($owner)->post(route('collaboration.applications.messages.store', $application), [
            'body' => 'Yes. I will send the project brief before the call.',
        ])->assertRedirect();
        $this->actingAs($stranger)->post(route('collaboration.applications.messages.store', $application), [
            'body' => 'I should not be able to join this conversation.',
        ])->assertForbidden();

        $this->assertDatabaseHas('collaboration_comments', [
            'collaboration_application_id' => $application->id,
            'user_id' => $candidate->id,
        ]);
        $this->assertSame(0, $opening->comments()->count());
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $owner->id,
            'text' => __('ui.notifications.collaboration_message', [
                'user' => $candidate->name,
                'title' => $opening->title,
            ]),
        ]);

        foreach ([$owner, $candidate] as $participant) {
            $this->actingAs($participant)->get(route('collaboration.show', $opening))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Collaboration')
                    ->has('applications.0.messages', 2)
                    ->where('applications.0.messages.0.body', 'Could we start with a short planning call next Tuesday?'));
        }
        $this->actingAs($stranger)->get(route('collaboration.show', $opening))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Collaboration')
                ->has('applications', 0));
    }

    public function test_people_can_comment_on_a_collaboration_and_remove_their_comment(): void
    {
        $owner = $this->eligibleUser();
        $candidate = $this->eligibleUser();
        $project = Post::factory()->for($owner)->create();
        $opening = $this->collaborationRequest($owner, $project);

        $this->post(route('collaboration.comments.store', $opening), ['body' => 'Can I join?'])
            ->assertRedirect(route('login'));

        $this->actingAs($candidate)->post(route('collaboration.comments.store', $opening), [
            'body' => 'Would you be open to asynchronous collaboration across time zones?',
        ])->assertRedirect();

        $comment = CollaborationComment::query()->firstOrFail();
        $this->assertDatabaseHas('collaboration_comments', [
            'id' => $comment->id,
            'collaboration_request_id' => $opening->id,
            'user_id' => $candidate->id,
        ]);
        $this->actingAs($candidate)->get(route('collaboration.show', $opening))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Collaboration')
                ->where('comments.0.id', $comment->id)
                ->where('comments.0.body', $comment->body)
                ->where('comments.0.can_edit', true)
                ->where('comments.0.can_delete', true));
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $owner->id,
            'text' => __('ui.notifications.comment_added', [
                'user' => $candidate->name,
                'title' => $opening->title,
            ]),
        ]);

        $this->actingAs($owner)
            ->delete(route('collaboration.comments.destroy', $comment))
            ->assertForbidden();
        $this->actingAs($candidate)->patch(route('collaboration.comments.update', $comment), [
            'body' => 'Would you be open to async collaboration across nearby time zones?',
        ])->assertRedirect();
        $this->assertDatabaseHas('collaboration_comments', ['id' => $comment->id, 'body' => 'Would you be open to async collaboration across nearby time zones?']);
        $this->actingAs($candidate)
            ->delete(route('collaboration.comments.destroy', $comment))
            ->assertRedirect();
        $this->assertDatabaseMissing('collaboration_comments', ['id' => $comment->id]);
    }

    public function test_moderator_can_remove_a_collaboration_comment(): void
    {
        $owner = $this->eligibleUser();
        $author = $this->eligibleUser();
        $moderator = User::factory()->create(['role' => 'moderator']);
        $project = Post::factory()->for($owner)->create();
        $opening = $this->collaborationRequest($owner, $project);
        $comment = CollaborationComment::create([
            'collaboration_request_id' => $opening->id,
            'user_id' => $author->id,
            'body' => 'A comment that needs moderation.',
        ]);

        $this->actingAs($moderator)
            ->delete(route('collaboration.comments.destroy', $comment))
            ->assertRedirect();
        $this->assertDatabaseMissing('collaboration_comments', ['id' => $comment->id]);
    }

    public function test_people_can_report_collaborations_and_their_comments(): void
    {
        $owner = $this->eligibleUser();
        $reporter = $this->eligibleUser();
        $admin = User::factory()->create(['role' => 'admin']);
        $post = Post::factory()->for($owner)->create();
        $opening = $this->collaborationRequest($owner, $post, ['title' => 'Reported collaboration']);
        $comment = CollaborationComment::create([
            'collaboration_request_id' => $opening->id,
            'user_id' => $owner->id,
            'body' => 'A reportable collaboration comment.',
        ]);

        $this->actingAs($reporter)->postJson(route('reports.store'), [
            'content_type' => 'collaboration',
            'content_id' => (string) $opening->id,
            'reason' => 'other',
            'details' => 'The collaboration details need moderator review.',
        ])->assertOk();
        $this->actingAs($reporter)->postJson(route('reports.store'), [
            'content_type' => 'collaboration_comment',
            'content_id' => (string) $comment->id,
            'reason' => 'abuse',
        ])->assertOk();

        $this->assertDatabaseHas('content_reports', [
            'content_type' => 'collaboration',
            'content_id' => (string) $opening->id,
        ]);
        $this->assertDatabaseHas('content_reports', [
            'content_type' => 'collaboration_comment',
            'content_id' => (string) $comment->id,
        ]);
        $this->actingAs($admin)->get(route('admin.tools', ['tab' => 'moderation']))
            ->assertOk()
            ->assertSee($opening->title)
            ->assertSee($comment->body);

        $this->actingAs($admin)->post(route('admin.collaboration-comments.reports.dismiss', $comment))
            ->assertRedirect(route('admin', ['tab' => 'moderation']));
        $this->actingAs($admin)->post(route('admin.collaborations.reports.dismiss', $opening))
            ->assertRedirect(route('admin', ['tab' => 'moderation']));
        $this->assertDatabaseHas('content_reports', [
            'content_type' => 'collaboration',
            'content_id' => (string) $opening->id,
            'resolved_status' => 'rejected',
        ]);
        $this->assertDatabaseHas('content_reports', [
            'content_type' => 'collaboration_comment',
            'content_id' => (string) $comment->id,
            'resolved_status' => 'rejected',
        ]);
    }

    public function test_collaboration_options_follow_the_current_locale(): void
    {
        app()->setLocale('fi');

        $this->get(route('collaboration'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Collaborations')
                ->where('roles.illustrator', __('ui.collaboration.roles.illustrator'))
                ->where('availability.flexible', __('ui.collaboration.availability_options.flexible')));
    }

    private function eligibleUser(): User
    {
        return User::factory()->create(['created_at' => now()->subHour()]);
    }

    private function collaborationRequest(User $owner, Post $post, array $attributes = []): CollaborationRequest
    {
        return CollaborationRequest::create(array_merge([
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'title' => 'Looking for a collaborator',
            'role' => 'illustrator',
            'skills' => ['drawing'],
            'availability' => 'flexible',
            'format' => 'remote',
            'summary' => $this->goodText(),
            'status' => 'open',
            'expires_at' => now()->addMonth(),
        ], $attributes));
    }

    private function goodText(string $start = 'We are building a small creative project and want a thoughtful collaborator who can contribute reliably.'): string
    {
        return $start.' The scope is clear, the existing material is ready to review, and we will agree on milestones together before any work begins.';
    }
}
