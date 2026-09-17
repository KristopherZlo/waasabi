<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_create_screen_separates_work_project_and_help(): void
    {
        $this->get(route('create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Create'));
    }

    public function test_standalone_work_can_become_a_project_without_changing_its_address(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'created_at' => now()->subHour(),
        ]);

        $this->actingAs($user)->post(route('publish.store'), [
            'publish_type' => 'post',
            'is_project' => false,
            'title' => 'One evening poster study',
            'body' => 'A standalone poster study made to explore a small visual idea.',
            'feedback_mode' => 'sharing',
            'visibility' => 'public',
            'publish_action' => 'publish',
        ])->assertRedirect();

        $work = Post::where('title', 'One evening poster study')->firstOrFail();
        $this->assertFalse($work->is_project);
        $this->get(route('project', $work->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Work')
                ->where('work.is_project', false)
                ->where('isOwner', true));
        $this->get(route('projects.updates.create', $work->slug))->assertNotFound();

        $this->post(route('projects.start', $work))->assertRedirect(route('project', $work->slug));
        $this->assertTrue($work->fresh()->is_project);
        $this->get(route('projects.updates.create', $work->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Editor')->where('kind', 'update'));
    }

    public function test_question_discussion_uses_the_same_safe_comment_flow(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'created_at' => now()->subHour(),
        ]);
        $question = Post::factory()->for($user)->question()->create();

        $this->actingAs($user)->post(route('questions.comments.store', $question->slug), [
            'body' => 'Here is a concrete answer from the community.',
        ])->assertOk();

        $this->assertDatabaseHas('post_comments', [
            'post_id' => $question->id,
            'body' => 'Here is a concrete answer from the community.',
        ]);
    }
}
