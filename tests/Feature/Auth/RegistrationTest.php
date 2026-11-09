<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registration_is_available_by_default(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Create your account');
    }

    public function test_registration_creates_a_shared_account_and_signs_it_in(): void
    {
        Event::fake([Registered::class]);

        $response = $this->post(route('register.store'), [
            'name' => 'Solar User',
            'email' => 'SOLAR@example.com',
            'password' => 'test-password',
            'password_confirmation' => 'test-password',
            'email_verified_at' => '2026-01-01 00:00:00',
        ]);

        $response->assertRedirectToRoute('dashboard');
        $this->assertDatabaseHas('users', ['name' => 'Solar User', 'email' => 'solar@example.com']);
        $user = User::where('email', 'solar@example.com')->sole();
        $this->assertTrue(Hash::check('test-password', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        Event::assertDispatched(Registered::class, fn (Registered $event): bool => $event->user->is($user));
    }

    public function test_registration_requires_name_email_and_password(): void
    {
        $this->post(route('register.store'), [])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @param  array<string, string>  $input
     */
    #[DataProvider('invalidRegistrationInputs')]
    public function test_invalid_registration_input_does_not_create_an_account(array $input, string $field, string $message): void
    {
        $payload = array_replace([
            'name' => 'Solar User',
            'email' => 'solar@example.com',
            'password' => 'test-password',
            'password_confirmation' => 'test-password',
        ], $input);

        $this->post(route('register.store'), $payload)->assertSessionHasErrors([$field => $message]);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function invalidRegistrationInputs(): array
    {
        return [
            'name is too long' => [['name' => str_repeat('a', 256)], 'name', 'The name field must not be greater than 255 characters.'],
            'email is invalid' => [['email' => 'not-an-email'], 'email', 'The email field must be a valid email address.'],
            'password is too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'password', 'The password field must be at least 8 characters.'],
            'confirmation differs' => [['password_confirmation' => 'different-password'], 'password', 'The password field confirmation does not match.'],
        ];
    }

    public function test_an_existing_email_cannot_be_registered_again(): void
    {
        User::factory()->create(['email' => 'solar@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Another User',
            'email' => 'SOLAR@example.com',
            'password' => 'test-password',
            'password_confirmation' => 'test-password',
        ])->assertSessionHasErrors(['email' => 'The email has already been taken.']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_disabling_registration_removes_the_routes_and_signup_link(): void
    {
        $environment = Env::getRepository();
        $previous = $environment->get('AUTH_REGISTRATION_ENABLED');
        $environment->set('AUTH_REGISTRATION_ENABLED', 'false');

        try {
            $this->refreshApplication();

            $this->get('/register')->assertNotFound();
            $this->post('/register', [])->assertNotFound();
            $this->get(route('login'))->assertOk()->assertDontSee('Create an account');
        } finally {
            $environment->clear('AUTH_REGISTRATION_ENABLED');
            if ($previous !== null) {
                $environment->set('AUTH_REGISTRATION_ENABLED', $previous);
            }
        }
    }
}
