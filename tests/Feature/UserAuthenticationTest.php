<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class UserAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_public_registration_always_creates_a_citizen_and_sends_verification(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'Nouvel utilisateur',
            'email' => 'new@example.test',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'role' => 'citizen']);
        Notification::assertSentTo(User::where('email', 'new@example.test')->first(), VerifyEmail::class);
    }

    public function test_password_reset_link_is_sent_without_exposing_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertSessionHas('success');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unverified_users_cannot_access_the_admin_area(): void
    {
        $user = User::factory()->create(['role' => 'manager', 'email_verified_at' => null]);

        $this->actingAs($user)->get(route('projects.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_valid_signed_verification_link_marks_email_as_verified(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $verificationUrl = (new VerifyEmail())->toMail($user)->actionUrl;

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_two_factor_challenge_requires_a_valid_totp_code(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $secret = app(Google2FA::class)->generateSecretKey();
        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        $this->post(route('two-factor.login.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->post(route('two-factor.login.store'), ['code' => $code])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }
}
