<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_can_request_a_password_reset(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee('Forgot your password?');
    }

    public function test_the_reset_form_contains_the_token_and_email(): void
    {
        $this->get(route('password.reset', ['token' => 'test-token', 'email' => 'solar@example.com']))
            ->assertOk()
            ->assertSee('Choose a new password')
            ->assertSee('value="test-token"', false)
            ->assertSee('value="solar@example.com"', false);
    }

    public function test_a_reset_request_dispatches_a_notification_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        Notification::fake();

        $response = $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email]);

        $response->assertRedirectToRoute('password.request')->assertSessionHas('status', __('passwords.sent'));
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            return Password::tokenExists($user, $notification->token);
        });
    }

    public function test_invalid_email_does_not_dispatch_a_reset_notification(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'invalid-email'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_a_valid_token_resets_the_password_and_is_consumed(): void
    {
        $user = User::factory()->create(['password' => 'old-test-password']);
        $token = Password::createToken($user);
        Event::fake([PasswordReset::class]);

        $response = $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-test-password',
            'password_confirmation' => 'new-test-password',
        ]);

        $response->assertRedirectToRoute('login')->assertSessionHas('status', __('passwords.reset'));
        $this->assertTrue(Hash::check('new-test-password', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest();
        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($user));
    }

    public function test_an_invalid_token_cannot_change_a_password(): void
    {
        $user = User::factory()->create(['password' => 'old-test-password']);
        Password::createToken($user);

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-test-password',
            'password_confirmation' => 'new-test-password',
        ])->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertTrue(Hash::check('old-test-password', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_an_expired_token_cannot_change_a_password(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['password' => 'old-test-password']);
        $token = Password::createToken($user);
        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->post(route('password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-test-password',
            'password_confirmation' => 'new-test-password',
        ])->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertTrue(Hash::check('old-test-password', $user->fresh()->password));
        $this->assertGuest();
    }

    /**
     * @param  array<string, string>  $input
     */
    #[DataProvider('invalidPasswords')]
    public function test_reset_password_validation_preserves_the_existing_password(array $input, string $message): void
    {
        $user = User::factory()->create(['password' => 'old-test-password']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), array_merge([
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-test-password',
            'password_confirmation' => 'new-test-password',
        ], $input))->assertSessionHasErrors(['password' => $message]);

        $this->assertTrue(Hash::check('old-test-password', $user->fresh()->password));
        $this->assertTrue(Password::tokenExists($user, $token));
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidPasswords(): array
    {
        return [
            'password is too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'The password field must be at least 8 characters.'],
            'confirmation differs' => [['password_confirmation' => 'different-password'], 'The password field confirmation does not match.'],
        ];
    }
}
