<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response->assertRedirect(route('profile.show', $user->slug));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'role' => 'user',
        ]);
    }

    public function test_admin_can_change_own_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->from('/profile/settings')->post('/profile/settings', [
            'name' => 'Admin Updated',
            'avatar' => 'https://example.com/avatar.png',
            'bio' => 'Admin bio.',
            'role' => 'maker',
        ]);

        $response->assertRedirect(route('profile.show', $admin->slug));
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'maker',
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
}
