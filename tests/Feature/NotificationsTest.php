<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_notifications(): void
    {
        $this->get('/notifications')->assertRedirect(route('login'));
    }

    public function test_user_can_view_notifications(): void
    {
        $user = User::factory()->create();
        UserNotification::create([
            'user_id' => $user->id,
            'type' => 'Update',
            'text' => 'Welcome!',
            'link' => '/support',
        ]);

        $this->actingAs($user)->get('/notifications')->assertOk();
    }

    public function test_user_can_mark_notification_read(): void
    {
        $user = User::factory()->create();
        $notification = UserNotification::create([
            'user_id' => $user->id,
            'type' => 'Update',
            'text' => 'New reply',
            'link' => '/support',
        ]);

        $response = $this->actingAs($user)->postJson('/notifications/' . $notification->id . '/read');

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseMissing('user_notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_user_can_mark_all_notifications_read(): void
    {
        $user = User::factory()->create();
        UserNotification::create([
            'user_id' => $user->id,
            'type' => 'Update',
            'text' => 'First',
        ]);
        UserNotification::create([
            'user_id' => $user->id,
            'type' => 'Update',
            'text' => 'Second',
        ]);

        $response = $this->actingAs($user)->postJson('/notifications/read-all');

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(0, UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count());
    }
}
