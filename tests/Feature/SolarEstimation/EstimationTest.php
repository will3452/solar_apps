<?php

namespace Tests\Feature\SolarEstimation;

use App\Models\SolarEstimation\CatalogItem;
use App\Models\SolarEstimation\Estimate;
use App\Models\User;
use App\Services\SolarEstimation\EstimateCalculator;
use Database\Seeders\SolarWorkbookSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EstimationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function workbookInput(): array
    {
        $this->seed(SolarWorkbookSeeder::class);

        return Estimate::where('reference', 'WORKBOOK-EXAMPLE')->sole()->inputs;
    }

    private function calculate(array $input): array
    {
        return app(EstimateCalculator::class)->calculate($input);
    }

    public function test_seed_is_idempotent_preserves_edits_and_creates_no_user_credentials(): void
    {
        $this->workbookInput();
        CatalogItem::where('sku', 'BOM-24')->update(['buying_cost' => 6123]);

        $this->seed(SolarWorkbookSeeder::class);

        $this->assertDatabaseCount('solar_catalog_items', 37);
        $this->assertDatabaseCount('solar_estimates', 1);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('solar_catalog_items', ['sku' => 'BOM-24', 'buying_cost' => 6123]);
        $this->assertDatabaseMissing('solar_catalog_items', ['sku' => 'BOM-27']);
    }

    public function test_sizing_keeps_energy_power_and_physical_counts_distinct(): void
    {
        $input = $this->workbookInput();

        $result = $this->calculate($input)['sizing'];

        $this->assertSame(4, $result['panel_count']);
        $this->assertSame(2, $result['battery_count']);
        $this->assertEqualsWithDelta(2.54, $result['pv_kwp'], 0.0001);
        $this->assertSame(3, $result['inverter_count']);
        $this->assertEqualsWithDelta(9, $result['inverter_kw'], 0.0001);
        $this->assertEqualsWithDelta(8.192, $result['battery_kwh'], 0.0001);
        $this->assertEqualsWithDelta(4650, $result['monthly_savings'], 0.001);
    }

    public function test_larger_consumption_recalculates_material_dependencies(): void
    {
        $input = $this->workbookInput();
        $input['monthly_kwh'] = 600;

        $lines = collect($this->calculate($input)['lines'])->keyBy('sku');

        $this->assertEqualsWithDelta(8, $lines['BOM-28']['quantity'], 0.001);
        $this->assertEqualsWithDelta(24, $lines['BOM-29']['quantity'], 0.001);
        $this->assertEqualsWithDelta(16, $lines['BOM-30']['quantity'], 0.001);
        $this->assertEqualsWithDelta(8, $lines['BOM-33']['quantity'], 0.001);
        $this->assertEqualsWithDelta(32, $lines['BOM-57']['quantity'], 0.001);
    }

    public function test_disabling_batteries_removes_battery_and_backup_accessories(): void
    {
        $input = $this->workbookInput();
        $input['battery_enabled'] = false;

        $result = $this->calculate($input);

        $this->assertSame(0, $result['sizing']['battery_count']);
        $skus = array_column($result['lines'], 'sku');
        foreach (['BOM-26', 'BOM-54', 'BOM-55', 'BOM-56', 'BOM-57'] as $sku) {
            $this->assertNotContains($sku, $skus);
        }
        $this->assertSame('not_needed', $result['cables']['battery']['status']);
    }

    public function test_shared_dependencies_are_not_charged_twice_for_multiple_parents_or_inverters(): void
    {
        $input = $this->workbookInput();
        $panel = CatalogItem::where('sku', 'BOM-24')->sole();
        $shared = CatalogItem::where('sku', 'BOM-38')->sole();
        $panel->dependencies()->attach($shared);

        $lines = collect($this->calculate($input)['lines']);

        $this->assertCount(1, $lines->where('sku', 'BOM-38'));
        $this->assertEqualsWithDelta(1, $lines->firstWhere('sku', 'BOM-38')['quantity'], 0.001);
        $this->assertEqualsWithDelta(15, $lines->firstWhere('sku', 'BOM-35')['quantity'], 0.001);
    }

    public function test_margin_discount_and_tax_use_the_documented_pricing_order(): void
    {
        $input = $this->workbookInput();
        CatalogItem::query()->update(['buying_cost' => 0]);
        CatalogItem::where('sku', 'BOM-24')->update(['buying_cost' => 100]);
        $input['panel_count_override'] = 1;
        $input['battery_enabled'] = false;
        $input['margin_percent'] = 20;
        $input['discount_amount'] = 5;
        $input['tax_percent'] = 10;

        $pricing = $this->calculate($input)['pricing'];

        $this->assertSame(10000, $pricing['buying_cents']);
        $this->assertSame(12500, $pricing['selling_cents']);
        $this->assertSame(500, $pricing['discount_cents']);
        $this->assertSame(1200, $pricing['tax_cents']);
        $this->assertSame(13200, $pricing['total_cents']);
    }

    public function test_source_unit_ambiguities_are_retained_as_incomplete(): void
    {
        $input = $this->workbookInput();

        $result = $this->calculate($input);
        $lines = collect($result['lines'])->keyBy('sku');

        $this->assertSame('roll', $lines['BOM-42']['unit']);
        $this->assertEqualsWithDelta(2, $lines['BOM-42']['quantity'], 0.001);
        $this->assertSame('pcs', $lines['BOM-56']['unit']);
        $this->assertSame('incomplete', $lines['BOM-56']['status']);
        $this->assertSame('incomplete', $lines['BOM-43']['status']);
        $this->assertSame('incomplete', $result['cables']['pv']['status']);
        $this->assertNotEmpty($result['cables']['pv']['missing']);
    }

    public function test_zero_consumption_and_zero_savings_do_not_create_invalid_payback(): void
    {
        $input = $this->workbookInput();
        $input['monthly_kwh'] = 0;

        $result = $this->calculate($input);

        $this->assertSame(0, $result['sizing']['panel_count']);
        $this->assertSame(0, $result['sizing']['battery_count']);
        $this->assertNull($result['sizing']['payback_months']);
    }

    public function test_explicit_zero_adjustment_is_not_replaced_with_the_automatic_quantity(): void
    {
        $input = $this->workbookInput();
        $rail = CatalogItem::where('sku', 'BOM-28')->sole();
        $input['overrides'][$rail->id] = ['quantity' => 0];

        $line = collect($this->calculate($input)['lines'])->firstWhere('sku', 'BOM-28');

        $this->assertEqualsWithDelta(0, $line['quantity'], 0.001);
        $this->assertSame(0, $line['buying_cents']);
    }

    public function test_additional_work_is_included_in_buying_and_selling_totals(): void
    {
        $input = $this->workbookInput();
        $original = $this->calculate($input);
        $input['additional_works'] = [['description' => 'Site access work', 'quantity' => 2, 'unit' => 'lot', 'unit_cost' => 1000]];

        $result = $this->calculate($input);

        $this->assertSame($original['pricing']['buying_cents'] + 200000, $result['pricing']['buying_cents']);
        $this->assertSame('Site access work', collect($result['lines'])->last()['name']);
    }

    public function test_authenticated_users_can_save_edit_preview_and_print_an_estimate(): void
    {
        $input = $this->workbookInput();
        $input['customer_name'] = 'Review customer <script>unsafe</script>';
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('modules.solar-estimation.estimates.store'), $input);

        $estimate = Estimate::where('reference', '!=', 'WORKBOOK-EXAMPLE')->sole();
        $response->assertRedirectToRoute('modules.solar-estimation.estimates.show', $estimate);
        $this->assertSame($user->id, $estimate->created_by);
        $this->get(route('modules.solar-estimation.estimates.show', $estimate))->assertOk()->assertSee($input['customer_name'])->assertDontSee($input['customer_name'], false);
        $this->get(route('modules.solar-estimation.estimates.edit', $estimate))->assertOk()->assertSee('Save and review materials');
        $this->postJson(route('modules.solar-estimation.estimates.preview-saved', $estimate), $input)->assertOk()->assertJsonStructure(['html']);
        $this->get(route('modules.solar-estimation.estimates.proposal', $estimate))->assertOk()->assertSee('Solar system proposal')->assertDontSee('Total buying cost')->assertDontSee('Gross margin');
    }

    public function test_saved_prices_remain_stable_until_explicitly_refreshed(): void
    {
        $input = $this->workbookInput();
        $estimate = Estimate::where('reference', 'WORKBOOK-EXAMPLE')->sole();
        $total = $estimate->snapshot['pricing']['total_cents'];
        CatalogItem::where('sku', 'BOM-24')->update(['buying_cost' => 6800]);
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('modules.solar-estimation.estimates.update', $estimate), $input)->assertSessionHasNoErrors();

        $this->assertSame($total, $estimate->fresh()->snapshot['pricing']['total_cents']);
        $input['refresh_catalog_prices'] = true;
        $this->put(route('modules.solar-estimation.estimates.update', $estimate), $input)->assertSessionHasNoErrors();
        $line = collect($estimate->fresh()->snapshot['lines'])->firstWhere('sku', 'BOM-24');
        $this->assertEqualsWithDelta(6800, $line['unit_cost'], 0.001);
        $this->assertGreaterThan($total, $estimate->fresh()->snapshot['pricing']['total_cents']);
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_do_not_save_an_estimate(string $field, mixed $value): void
    {
        $input = $this->workbookInput();
        $input[$field] = $value;
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('modules.solar-estimation.estimates.store'), $input)->assertSessionHasErrors($field);

        $this->assertDatabaseCount('solar_estimates', 1);
    }

    public static function invalidInputs(): array
    {
        return ['zero sun hours' => ['sun_hours', 0], 'negative energy' => ['monthly_kwh', -1], 'zero battery depth' => ['dod_percent', 0], 'margin of 100 percent' => ['margin_percent', 100], 'fractional inverter count' => ['inverter_count', 1.5]];
    }

    public function test_dependency_cycles_are_rejected_without_saving_the_link(): void
    {
        $this->workbookInput();
        $rail = CatalogItem::where('sku', 'BOM-28')->sole();
        $panel = CatalogItem::where('sku', 'BOM-24')->sole();
        $input = array_merge($rail->toArray(), ['dependencies' => [$panel->id], 'specs_json' => '{}']);
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('modules.solar-estimation.catalog.update', $rail), $input)->assertSessionHasErrors('dependencies');

        $this->assertDatabaseMissing('solar_catalog_dependencies', ['parent_id' => $rail->id, 'child_id' => $panel->id]);
    }

    public function test_catalog_price_and_quantity_rule_edits_preserve_source_provenance(): void
    {
        $this->workbookInput();
        $rail = CatalogItem::where('sku', 'BOM-28')->sole();
        $source = $rail->source;
        $input = array_merge($rail->toArray(), ['buying_cost' => 800, 'quantity_factor' => 2, 'specs_json' => '{}']);
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('modules.solar-estimation.catalog.update', $rail), $input)->assertRedirectToRoute('modules.solar-estimation.catalog.index');

        $this->assertSame($source, $rail->fresh()->source);
        $this->assertEqualsWithDelta(800, $rail->fresh()->buying_cost, 0.001);
        $line = collect($this->calculate(Estimate::first()->inputs)['lines'])->firstWhere('sku', 'BOM-28');
        $this->assertEqualsWithDelta(8, $line['quantity'], 0.001);
        $this->get(route('modules.solar-estimation.catalog.edit', $rail))->assertOk()->assertSee('Automatic quantity and dependent materials');
    }

    public function test_main_equipment_quantity_cannot_be_changed_without_recalculating_sizing(): void
    {
        $input = $this->workbookInput();
        $input['overrides'][$input['panel_id']] = ['quantity' => 1];
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('modules.solar-estimation.estimates.store'), $input)->assertSessionHasErrors('overrides');

        $this->assertDatabaseCount('solar_estimates', 1);
    }

    public function test_new_main_products_use_sized_quantities_even_with_a_fixed_catalog_default(): void
    {
        $input = $this->workbookInput();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('modules.solar-estimation.catalog.store'), [
            'sku' => 'TEST-PANEL', 'name' => 'Synthetic test panel', 'category' => 'Test fixture', 'unit' => 'pcs',
            'buying_cost' => 1000, 'role' => 'panel', 'quantity_rule' => 'fixed', 'default_quantity' => 1,
            'quantity_factor' => 1, 'group_size' => 6, 'active' => 1, 'specification_status' => 'source_unverified',
            'spec_power_w' => 500, 'specs_json' => '{}',
        ])->assertRedirectToRoute('modules.solar-estimation.catalog.index');

        $panel = CatalogItem::where('sku', 'TEST-PANEL')->sole();
        $input['panel_id'] = $panel->id;
        $result = $this->calculate($input);
        $this->assertSame(5, $result['sizing']['panel_count']);
        $this->assertEqualsWithDelta(5, collect($result['lines'])->firstWhere('product_id', $panel->id)['quantity'], 0.001);
    }

    public function test_a_missing_buying_price_stays_pending_in_the_customer_proposal(): void
    {
        $input = $this->workbookInput();
        CatalogItem::where('sku', 'BOM-24')->update(['buying_cost' => null]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('modules.solar-estimation.estimates.store'), $input)->assertSessionHasNoErrors();

        $estimate = Estimate::where('reference', '!=', 'WORKBOOK-EXAMPLE')->sole();
        $line = collect($estimate->snapshot['lines'])->firstWhere('sku', 'BOM-24');
        $this->assertNull($line['unit_cost']);
        $this->assertSame('incomplete', $line['status']);
        $this->get(route('modules.solar-estimation.estimates.proposal', $estimate))
            ->assertOk()->assertSee('Price pending')->assertSee('Known total (prices pending)');
    }

    public function test_a_battery_product_is_not_required_when_storage_is_disabled(): void
    {
        $input = $this->workbookInput();
        $input['battery_enabled'] = false;
        $input['battery_id'] = null;
        CatalogItem::where('role', 'battery')->update(['active' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('modules.solar-estimation.estimates.store'), $input)->assertSessionHasNoErrors();

        $estimate = Estimate::where('reference', '!=', 'WORKBOOK-EXAMPLE')->sole();
        $this->assertSame(0, $estimate->snapshot['sizing']['battery_count']);
    }

    #[DataProvider('protectedPages')]
    public function test_estimate_catalog_and_proposal_pages_require_authentication(string $route, bool $record): void
    {
        $this->workbookInput();

        $this->get(route($route, $record ? 1 : []))->assertRedirectToRoute('login');
    }

    public static function protectedPages(): array
    {
        return [
            'module' => ['modules.solar-estimation.index', false], 'create' => ['modules.solar-estimation.estimates.create', false], 'review' => ['modules.solar-estimation.estimates.show', true], 'edit' => ['modules.solar-estimation.estimates.edit', true], 'proposal' => ['modules.solar-estimation.estimates.proposal', true], 'catalog' => ['modules.solar-estimation.catalog.index', false], 'edit catalog' => ['modules.solar-estimation.catalog.edit', true],
        ];
    }

    public function test_guest_writes_and_previews_are_refused(): void
    {
        $this->post(route('modules.solar-estimation.estimates.store'), [])->assertRedirectToRoute('login');
        $this->postJson(route('modules.solar-estimation.estimates.preview'), [])->assertUnauthorized();
        $this->post(route('modules.solar-estimation.catalog.store'), [])->assertRedirectToRoute('login');
    }
}
