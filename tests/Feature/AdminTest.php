<?php

namespace Tests\Feature;

use App\Models\CollaborationApplication;
use App\Models\CollaborationComment;
use App\Models\CollaborationRequest;
use App\Models\ContentReport;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Moderation')
            ->has('works.data'));
        $this->actingAs($admin)->get(route('admin.tools'))->assertOk()
            ->assertSee('class="admin-app"', false)
            ->assertSee(__('ui.admin.overview'));
    }

    public function test_content_sections_keep_their_full_unreported_lists(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $post = Post::factory()->create();
        $comment = PostComment::create([
            'post_id' => $post->id,
            'post_slug' => $post->slug,
            'user_id' => $admin->id,
            'body' => 'Visible in the complete admin comment list.',
            'useful' => 0,
        ]);
        $review = PostReview::create([
            'post_id' => $post->id,
            'post_slug' => $post->slug,
            'user_id' => $admin->id,
            'improve' => 'Visible in the complete admin review list.',
            'why' => 'It should not depend on reports.',
            'how' => 'Keep moderation collections separate.',
        ]);

        $this->actingAs($admin)->get(route('admin.tools', ['tab' => 'comments']))
            ->assertOk()
            ->assertSee($comment->body);
        $this->actingAs($admin)->get(route('admin.tools', ['tab' => 'reviews']))
            ->assertOk()
            ->assertSee($review->improve);
    }

    public function test_admin_create_command_requires_explicit_credentials(): void
    {
        $this->artisan('admin:create', [
            'email' => 'admin@example.test',
            '--name' => 'Project Admin',
            '--password' => 'Strong!Admin123',
        ])->assertSuccessful();

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('Strong!Admin123', $admin->password));
    }

    public function test_non_admin_forbidden_from_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }

    public function test_banned_staff_lose_privileged_read_access(): void
    {
        $bannedAdmin = User::factory()->create(['role' => 'admin', 'is_banned' => true]);
        $bannedModerator = User::factory()->create(['role' => 'moderator', 'is_banned' => true]);
        $bannedSupport = User::factory()->create(['role' => 'support', 'is_banned' => true]);
        $ticket = SupportTicket::factory()->create(['subject' => 'Private ticket from another user']);

        $this->actingAs($bannedAdmin)->get(route('admin'))->assertForbidden();
        $this->actingAs($bannedModerator)->get(route('admin'))->assertForbidden();
        $this->actingAs($bannedSupport)->get(route('support'))
            ->assertOk()
            ->assertDontSee($ticket->subject);
    }

    public function test_admin_can_update_user_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin)->post("/admin/users/{$user->id}/role", [
            'role' => 'maker',
        ]);

        $response->assertRedirect(route('admin', ['tab' => 'users', 'user' => $user->id]));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'maker',
        ]);
    }

    public function test_last_active_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'admin', 'is_banned' => true]);

        $this->actingAs($admin)->post("/admin/users/{$admin->id}/role", [
            'role' => 'user',
        ])->assertSessionHasErrors('role');

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_only_active_admin_can_manage_profile_badges(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $bannedAdmin = User::factory()->create(['role' => 'admin', 'is_banned' => true]);
        $user = User::factory()->create();
        $recipient = User::factory()->create();
        $route = route('profile.badges.grant', $recipient->slug);

        $this->actingAs($user)->postJson($route, ['badge_key' => 'beta'])->assertForbidden();
        $this->actingAs($bannedAdmin)->postJson($route, ['badge_key' => 'beta'])->assertForbidden();

        $response = $this->actingAs($admin)->postJson($route, ['badge_key' => 'beta'])
            ->assertOk()
            ->assertJsonPath('ok', true);
        $badgeId = (int) $response->json('badge_id');

        $this->assertDatabaseHas('user_badges', [
            'id' => $badgeId,
            'user_id' => $recipient->id,
            'badge_key' => 'beta',
            'issued_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('profile.badges.revoke', [$recipient->slug, $badgeId]))
            ->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertDatabaseMissing('user_badges', ['id' => $badgeId]);
    }

    public function test_admin_can_delete_comment_review_and_post(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        Storage::disk('public')->put('uploads/covers/admin-delete.webp', 'image');
        $post = Post::factory()->create([
            'type' => 'post',
            'cover_url' => 'storage/uploads/covers/admin-delete.webp',
        ]);
        $postReport = ContentReport::create([
            'user_id' => User::factory()->create()->id,
            'content_type' => 'post',
            'content_id' => (string) $post->id,
            'reason' => 'spam',
        ]);
        $comment = PostComment::create([
            'post_slug' => $post->slug,
            'user_id' => $admin->id,
            'body' => 'Admin comment',
            'section' => null,
            'useful' => 0,
            'parent_id' => null,
        ]);
        $review = PostReview::create([
            'post_slug' => $post->slug,
            'user_id' => $admin->id,
            'improve' => 'Improve this',
            'why' => 'Reason',
            'how' => 'Steps',
        ]);

        $this->actingAs($admin)
            ->from('/admin')
            ->delete("/admin/comments/{$comment->id}", ['reason' => 'Test cleanup'])
            ->assertRedirect(route('admin'));
        $this->assertDatabaseMissing('post_comments', ['id' => $comment->id]);

        $this->actingAs($admin)
            ->from('/admin')
            ->delete("/admin/reviews/{$review->id}", ['reason' => 'Test cleanup'])
            ->assertRedirect(route('admin'));
        $this->assertDatabaseMissing('post_reviews', ['id' => $review->id]);

        $this->actingAs($admin)
            ->from('/admin')
            ->delete("/admin/posts/{$post->id}", ['reason' => 'Test cleanup'])
            ->assertRedirect(route('admin'));
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertSame('confirmed', $postReport->fresh()->resolved_status);
        Storage::disk('public')->assertMissing('uploads/covers/admin-delete.webp');
    }

    public function test_admin_can_remove_flagged_media_and_its_references(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/covers/flagged.jpg', 'image');
        $path = 'storage/uploads/covers/flagged.jpg';
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['banner_url' => $path]);
        $post = Post::factory()->create([
            'user_id' => $owner->id,
            'cover_url' => $path,
            'album_urls' => [$path],
            'body_html' => '<p>Before</p><img src="/'.$path.'"><p>After</p>',
            'body_markdown' => 'Before ![flagged]('.$path.') After',
        ]);
        $report = ContentReport::create([
            'user_id' => $owner->id,
            'content_type' => 'content',
            'content_id' => $path,
            'reason' => 'admin_flag',
            'resolved_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.media.resolve', $report), ['action' => 'remove'])
            ->assertRedirect(route('admin', ['tab' => 'media']));

        Storage::disk('public')->assertMissing('uploads/covers/flagged.jpg');
        $this->assertNull($owner->fresh()->banner_url);
        $post->refresh();
        $this->assertNull($post->cover_url);
        $this->assertSame([], $post->album_urls);
        $this->assertStringNotContainsString($path, $post->body_html);
        $this->assertStringNotContainsString($path, $post->body_markdown);
        $this->assertSame('confirmed', $report->fresh()->resolved_status);
    }

    public function test_admin_can_bulk_moderate_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $post = Post::factory()->create();

        $this->actingAs($admin)->post(route('admin.content.bulk'), [
            'post_ids' => [$post->id],
            'action' => 'hide',
            'reason' => 'Unsafe public content',
        ])->assertRedirect(route('admin', ['tab' => 'content']));

        $post->refresh();
        $this->assertSame('hidden', $post->moderation_status);
        $this->assertTrue($post->is_hidden);

        $this->actingAs($admin)->post(route('admin.content.bulk'), [
            'post_ids' => [$post->id],
            'action' => 'restore',
        ])->assertRedirect(route('admin', ['tab' => 'content']));
        $this->assertSame('approved', $post->fresh()->moderation_status);
    }

    public function test_moderator_can_dismiss_a_false_report_without_hiding_content(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);
        $post = Post::factory()->create();
        $report = ContentReport::create([
            'user_id' => User::factory()->create()->id,
            'content_type' => 'post',
            'content_id' => (string) $post->id,
            'reason' => 'spam',
            'resolved_status' => 'pending',
        ]);

        $this->actingAs($moderator)
            ->postJson(route('moderation.reports.dismiss', ['type' => 'post', 'id' => $post->id]))
            ->assertOk()
            ->assertJsonPath('status', 'approved');

        $this->assertSame('rejected', $report->fresh()->resolved_status);
        $this->assertSame('approved', $post->fresh()->moderation_status);
        $this->assertDatabaseHas('moderation_logs', [
            'moderator_id' => $moderator->id,
            'action' => 'dismiss_report',
            'content_id' => (string) $post->id,
        ]);
    }

    public function test_moderator_cannot_delete_content_or_manage_admin_owned_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $moderator = User::factory()->create(['role' => 'moderator']);
        $regularPost = Post::factory()->create();
        $adminPost = Post::factory()->for($admin)->create();

        $this->actingAs($moderator)->post(route('admin.content.bulk'), [
            'post_ids' => [$regularPost->id],
            'action' => 'delete',
            'reason' => 'Delete attempt',
        ])->assertForbidden();
        $this->actingAs($moderator)->post(route('admin.content.bulk'), [
            'post_ids' => [$adminPost->id],
            'action' => 'hide',
            'reason' => 'Hide attempt',
        ])->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $regularPost->id]);
        $this->assertSame('approved', $adminPost->fresh()->moderation_status);
    }

    public function test_admin_can_manage_collaborations_responses_and_comments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create();
        $candidate = User::factory()->create();
        $post = Post::factory()->for($owner)->create();
        $collaboration = CollaborationRequest::create([
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'title' => 'Build a small documentary together',
            'role' => 'editor',
            'availability' => 'flexible',
            'format' => 'remote',
            'summary' => 'A clear collaboration summary for the administration test.',
            'status' => 'open',
        ]);
        $application = CollaborationApplication::create([
            'collaboration_request_id' => $collaboration->id,
            'user_id' => $candidate->id,
            'message' => 'I can edit the documentary and organize the source footage.',
            'status' => 'pending',
        ]);
        $comment = CollaborationComment::create([
            'collaboration_request_id' => $collaboration->id,
            'user_id' => $candidate->id,
            'body' => 'Which editing format do you use?',
        ]);

        $this->actingAs($admin)->get(route('admin.tools', ['tab' => 'collaborations', 'request' => $collaboration->id]))
            ->assertOk()
            ->assertSee($collaboration->title)
            ->assertSee($application->message)
            ->assertSee($comment->body);

        $this->actingAs($admin)->post(route('admin.collaborations.bulk'), [
            'request_ids' => [$collaboration->id],
            'action' => 'close',
            'reason' => 'Owner requested administrative closure',
        ])->assertRedirect(route('admin', ['tab' => 'collaborations']));
        $this->assertSame('closed', $collaboration->fresh()->status);
        $this->assertSame('closed', $application->fresh()->status);

        $this->actingAs($admin)->delete(route('admin.collaboration-comments.delete', $comment), [
            'reason' => 'Moderation cleanup',
        ])->assertRedirect();
        $this->assertDatabaseMissing('collaboration_comments', ['id' => $comment->id]);
    }

    public function test_admin_user_analytics_and_system_sections_have_real_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['name' => 'History Person', 'email' => 'history@example.test']);
        $post = Post::factory()->for($user)->create(['title' => 'A traceable project']);
        ContentReport::create([
            'user_id' => $user->id,
            'content_type' => 'post',
            'content_id' => (string) $post->id,
            'reason' => 'other',
            'resolved_status' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('admin.tools', ['tab' => 'users', 'user' => $user->id]))
            ->assertOk()
            ->assertSee($user->email)
            ->assertSee($post->title)
            ->assertSee(__('ui.admin.reports_submitted'));
        $this->actingAs($admin)->get(route('admin.tools', ['tab' => 'analytics']))
            ->assertOk()
            ->assertSee(__('ui.admin.analytics_period'))
            ->assertSee(now()->toDateString());
        $this->actingAs($admin)->get(route('admin.tools', ['tab' => 'system']))
            ->assertOk()
            ->assertSee(__('ui.admin.system_database'))
            ->assertSee(PHP_VERSION);
    }

    public function test_moderator_cannot_open_admin_only_sections(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);

        $this->actingAs($moderator)->get(route('admin.tools', ['tab' => 'users']))
            ->assertOk()
            ->assertDontSee(__('ui.admin.user_email'));
        $this->actingAs($moderator)->get(route('admin.tools', ['tab' => 'system']))
            ->assertOk()
            ->assertDontSee(__('ui.admin.system_database'));
    }
}
