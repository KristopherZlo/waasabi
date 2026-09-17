<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_logs_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Alex Tester',
            'email' => ' Alex@Example.COM ',
            'password' => 'Z9!qT1xP#9Lm',
            'password_confirmation' => 'Z9!qT1xP#9Lm',
            'accept_legal' => '1',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'alex@example.com',
            'role' => 'user',
        ]);
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->post('/login', [
            'email' => '  '.strtoupper($user->email).'  ',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('feed'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $user->id]);
    }

    public function test_password_reset_normalizes_the_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->post(route('password.email'), ['email' => ' RESET@EXAMPLE.COM '])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_clears_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('feed'));
        $this->assertGuest();
    }
}
