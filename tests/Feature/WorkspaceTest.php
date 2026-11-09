<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('protectedRoutes')]
    public function test_guests_are_redirected_to_login(string $route): void
    {
        $this->get(route($route))->assertRedirectToRoute('login');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'shared workspace' => ['dashboard'],
            'solar estimation module' => ['modules.solar-estimation.index'],
        ];
    }

    public function test_the_dashboard_lists_the_solar_estimation_module(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your workspace')
            ->assertSee('Solar Estimation Module')
            ->assertSee(route('modules.solar-estimation.index'))
            ->assertSee('Sign out');
    }

    public function test_signed_in_users_can_open_the_solar_estimation_module(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('modules.solar-estimation.index'))
            ->assertOk()
            ->assertSee('Solar Estimation Module')
            ->assertSee('New estimate')
            ->assertSee(route('dashboard'));
    }

    public function test_signed_in_users_are_sent_to_the_dashboard_from_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))->assertRedirectToRoute('dashboard');
    }

    public function test_the_module_registry_supplies_dashboard_and_navigation_entries(): void
    {
        $user = User::factory()->create();
        config(['modules.additional' => [
            'name' => 'Additional module',
            'description' => 'A module registered through configuration.',
            'route' => 'dashboard',
            'active' => 'additional.*',
        ]]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Additional module')
            ->assertSee('A module registered through configuration.');
        $this->get(route('modules.solar-estimation.index'))
            ->assertOk()
            ->assertSee('Additional module');
    }

    public function test_the_shared_header_escapes_the_account_name(): void
    {
        $name = '<script>alert("unsafe")</script>';
        $user = User::factory()->create(['name' => $name]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($name)
            ->assertDontSee($name, false);
    }
}
