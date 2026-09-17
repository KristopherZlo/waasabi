<?php

namespace Tests\Feature;

use App\Models\ContentReport;
use App\Models\Post;
use App\Models\UploadAsset;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Old!Password123')]);

        $this->actingAs($user)->patch('/account/password', [
            'current_password' => 'Old!Password123',
            'password' => 'New!Password456',
            'password_confirmation' => 'New!Password456',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('New!Password456', $user->fresh()->password));
    }

    public function test_password_reset_link_and_token_flow_work(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'maker@example.test']);

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::createToken($user);
        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Reset!Password456',
            'password_confirmation' => 'Reset!Password456',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('Reset!Password456', $user->fresh()->password));
    }

    public function test_account_export_contains_owned_projects(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create(['title' => 'Exported project']);

        $response = $this->actingAs($user)->get('/account/export');

        $response->assertOk()->assertHeader('content-type', 'application/json; charset=UTF-8');
        $this->assertStringContainsString($post->title, $response->streamedContent());
        $this->assertStringNotContainsString($user->password, $response->streamedContent());
    }

    public function test_account_deletion_removes_database_and_uploaded_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['password' => Hash::make('Delete!Password123')]);
        $post = Post::factory()->for($user)->create(['cover_url' => 'storage/uploads/covers/cover.webp']);
        Storage::disk('public')->put('uploads/covers/cover.webp', 'cover');
        Storage::disk('public')->put('uploads/editor/body.webp', 'body');
        UploadAsset::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'kind' => 'editor',
            'path' => 'storage/uploads/editor/body.webp',
        ]);
        $reporter = User::factory()->create();
        $postReport = ContentReport::create([
            'user_id' => $reporter->id,
            'content_type' => 'post',
            'content_id' => (string) $post->id,
            'reason' => 'spam',
        ]);
        $profileReport = ContentReport::create([
            'user_id' => $reporter->id,
            'content_type' => 'profile',
            'content_id' => (string) $user->id,
            'reason' => 'spam',
        ]);

        $this->actingAs($user)->delete('/account', ['password' => 'Delete!Password123'])
            ->assertRedirect(route('feed'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        Storage::disk('public')->assertMissing('uploads/covers/cover.webp');
        Storage::disk('public')->assertMissing('uploads/editor/body.webp');
        $this->assertSame('withdrawn', $postReport->fresh()->resolved_status);
        $this->assertSame('withdrawn', $profileReport->fresh()->resolved_status);
    }

    public function test_profile_media_deletion_rejects_path_traversal(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('secret.txt', 'keep');
        $user = User::factory()->create([
            'avatar' => 'storage/uploads/avatars/../../secret.txt',
            'created_at' => now()->subHour(),
        ]);

        $this->actingAs($user)->delete(route('profile.avatar.delete', $user->slug))->assertOk();

        Storage::disk('public')->assertExists('secret.txt');
    }

    public function test_last_administrator_cannot_delete_their_account(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('Admin!Password123'),
        ]);
        User::factory()->create(['role' => 'admin', 'is_banned' => true]);

        $this->actingAs($admin)->delete('/account', ['password' => 'Admin!Password123'])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_banned_user_can_delete_their_account(): void
    {
        $user = User::factory()->create([
            'is_banned' => true,
            'password' => Hash::make('Delete!Password123'),
        ]);

        $this->actingAs($user)->delete('/account', ['password' => 'Delete!Password123'])
            ->assertRedirect(route('feed'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
