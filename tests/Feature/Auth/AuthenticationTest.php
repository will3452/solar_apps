<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_can_view_the_login_screen(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Welcome back');
    }

    public function test_valid_credentials_sign_in_and_redirect_to_the_workspace(): void
    {
        $user = User::factory()->create(['password' => 'test-password']);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'test-password',
        ]);

        $response->assertRedirectToRoute('dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_returns_the_user_to_the_module_they_requested(): void
    {
        $user = User::factory()->create(['password' => 'test-password']);
        $this->get(route('modules.solar-estimation.index'))->assertRedirectToRoute('login');

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'test-password',
        ]);

        $response->assertRedirectToRoute('modules.solar-estimation.index');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_leave_the_visitor_signed_out(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ]);

        $response->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_remember_me_sets_a_remember_cookie(): void
    {
        $user = User::factory()->create(['password' => 'test-password', 'remember_token' => null]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'test-password',
            'remember' => '1',
        ]);

        $response->assertRedirectToRoute('dashboard');
        $response->assertCookie(auth()->guard()->getRecallerName());
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_repeated_login_attempts_are_throttled(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $credentials = ['email' => $user->email, 'password' => 'incorrect-password'];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('login.store'), $credentials)->assertUnprocessable();
        }

        $this->postJson(route('login.store'), $credentials)->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_malformed_email_input_returns_validation_errors(): void
    {
        $this->postJson(route('login.store'), [
            'email' => ['unexpected' => 'value'],
            'password' => 'test-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_signed_in_users_are_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('login'))->assertRedirectToRoute('dashboard');
    }

    public function test_signing_out_removes_access_to_the_workspace(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirectToRoute('login');
    }

    public function test_login_requires_a_csrf_token_outside_the_test_environment(): void
    {
        $this->app['env'] = 'local';

        $this->post(route('login.store'), [
            'email' => 'person@example.com',
            'password' => 'test-password',
        ])->assertStatus(419);

        $this->assertGuest();
    }
}
