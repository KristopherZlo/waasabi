<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_enable_and_confirm_two_factor_authentication(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Secret123!')]);

        $this->actingAs($user)->post(route('two-factor.enable'), ['current_password' => 'Secret123!'])
            ->assertRedirect(route('profile.settings').'#security');

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        $this->post(route('two-factor.confirm'), ['code' => (new Google2FA)->getCurrentOtp($user->two_factor_secret)])
            ->assertRedirect(route('profile.settings').'#security')
            ->assertSessionHas('two_factor_recovery_codes');

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_confirmed_user_must_complete_two_factor_challenge_to_login(): void
    {
        $service = app(TwoFactorService::class);
        $secret = $service->secret();
        $user = User::factory()->create([
            'password' => Hash::make('Secret123!'),
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $service->recoveryCodes(),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123!'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get(route('two-factor.challenge'))->assertInertia(fn (Assert $page) => $page
            ->component('Auth')->where('mode', 'two-factor'));

        $this->post(route('two-factor.verify'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
            ->assertRedirect(route('feed'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_code_is_single_use(): void
    {
        $service = app(TwoFactorService::class);
        $codes = $service->recoveryCodes();
        $user = User::factory()->create([
            'two_factor_secret' => $service->secret(),
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->assertTrue($service->verify($user, $codes[0]));
        $this->assertFalse($service->verify($user->fresh(), $codes[0]));
    }
}
