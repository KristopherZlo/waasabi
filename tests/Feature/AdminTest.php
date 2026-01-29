<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
    }

    public function test_non_admin_forbidden_from_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }

    public function test_admin_can_update_user_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin)->post("/admin/users/{$user->id}/role", [
            'role' => 'maker',
        ]);

        $response->assertRedirect(route('admin'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'maker',
        ]);
    }

    public function test_admin_can_delete_comment_review_and_post(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $post = Post::factory()->create(['type' => 'post']);
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
    }
}
