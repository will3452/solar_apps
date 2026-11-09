<?php

namespace Tests\Feature\SolarEstimation;

use App\Models\SolarEstimation\CatalogItem;
use App\Models\SolarEstimation\Estimate;
use App\Services\SolarEstimation\CableSelector;
use App\Services\SolarEstimation\EstimateCalculator;
use Database\Seeders\SolarWorkbookSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CableSelectorTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Synthetic test data only; these are not sourced cable ratings. */
    private function site(): array
    {
        return ['length_m' => 20, 'current_a' => 30, 'voltage_v' => 230, 'protection_a' => 40, 'temperature_factor' => 0.9, 'grouping_factor' => 1, 'installation_factor' => 1, 'terminal_min_mm2' => 2.5, 'terminal_max_mm2' => 16, 'max_drop_percent' => 3, 'phase' => 'single', 'conductors' => 2, 'runs' => 1, 'basis' => 'Synthetic unit-test design values, not installation data'];
    }

    private function cables(): Collection
    {
        return collect([[4, 35, 5], [6, 60, 3], [10, 80, 2]])->map(fn ($values) => CatalogItem::factory()->create([
            'name' => 'Synthetic test cable '.$values[0], 'role' => 'cable', 'unit' => 'm', 'specification_status' => 'reviewed',
            'specs' => ['section_mm2' => $values[0], 'ampacity_a' => $values[1], 'resistance_ohm_per_km' => $values[2], 'voltage_rating_v' => 1000, 'per_unit_length_m' => 1, 'allowed_circuits' => ['grid', 'backup'], 'provenance' => 'Synthetic fixture only'],
        ]));
    }

    public function test_chooses_the_smallest_documented_cable_passing_all_checks(): void
    {
        $cables = $this->cables();

        $result = app(CableSelector::class)->select('grid', $this->site(), $cables);

        $this->assertSame('candidate', $result['status']);
        $this->assertSame(6, $result['selected']['section_mm2']);
        $this->assertEqualsWithDelta(54, $result['selected']['corrected_ampacity_a'], 0.001);
        $this->assertEqualsWithDelta(1.565, $result['selected']['drop_percent'], 0.001);
        $this->assertEqualsWithDelta(40, $result['selected']['quantity'], 0.001);
    }

    public function test_site_length_combines_with_product_circuit_defaults(): void
    {
        $cables = $this->cables();
        $defaults = $this->site();
        unset($defaults['length_m']);

        $result = app(CableSelector::class)->select('grid', ['length_m' => 20], $cables, $defaults);

        $this->assertSame('candidate', $result['status']);
        $this->assertSame(6, $result['selected']['section_mm2']);
    }

    public function test_all_correction_factors_affect_ampacity_selection(): void
    {
        $cables = $this->cables();
        $site = array_replace($this->site(), ['temperature_factor' => 0.8, 'grouping_factor' => 0.8, 'installation_factor' => 0.8]);

        $result = app(CableSelector::class)->select('grid', $site, $cables);

        $this->assertSame(10, $result['selected']['section_mm2']);
        $this->assertEqualsWithDelta(40.96, $result['selected']['corrected_ampacity_a'], 0.001);
    }

    public function test_long_route_rejects_cables_exceeding_voltage_drop(): void
    {
        $cables = $this->cables();

        $result = app(CableSelector::class)->select('grid', array_replace($this->site(), ['length_m' => 100]), $cables);

        $this->assertSame('no_match', $result['status']);
        $this->assertStringContainsString('voltage drop', implode(' ', $result['rejected']));
    }

    public function test_voltage_rating_and_terminal_limits_are_enforced(): void
    {
        $cables = $this->cables();
        foreach ($cables as $cable) {
            $cable->specs = array_replace($cable->specs, ['voltage_rating_v' => 100]);
        }

        $result = app(CableSelector::class)->select('grid', $this->site(), $cables);

        $this->assertSame('no_match', $result['status']);
        $this->assertStringContainsString('voltage rating', implode(' ', $result['rejected']));
        $result = app(CableSelector::class)->select('grid', array_replace($this->site(), ['terminal_max_mm2' => 4]), $this->cables());
        $this->assertSame('no_match', $result['status']);
        $this->assertStringContainsString('terminal', implode(' ', $result['rejected']));
    }

    public function test_missing_circuit_data_is_incomplete_rather_than_zero(): void
    {
        $result = app(CableSelector::class)->select('grid', [], $this->cables());

        $this->assertSame('incomplete', $result['status']);
        $this->assertContains('One-way route length (m)', $result['missing']);
        $this->assertArrayNotHasKey('selected', $result);
    }

    public function test_unreviewed_specs_or_undefined_unit_length_do_not_produce_a_candidate(): void
    {
        $cables = $this->cables();
        foreach ($cables as $cable) {
            $cable->specs = array_replace($cable->specs, ['per_unit_length_m' => null]);
        }

        $result = app(CableSelector::class)->select('grid', $this->site(), $cables);

        $this->assertSame('incomplete', $result['status']);
        $this->assertNotEmpty($result['missing']);
    }

    public function test_documented_equipment_current_limits_are_enforced(): void
    {
        $result = app(CableSelector::class)->select('grid', $this->site(), $this->cables(), ['maximum_current_a' => 25]);

        $this->assertSame('no_match', $result['status']);
        $this->assertStringContainsString('equipment', $result['message']);
    }

    public function test_metres_are_rounded_up_to_whole_rolls_only_when_roll_length_is_known(): void
    {
        $cables = $this->cables();
        $cable = $cables[1];
        $cable->unit = 'roll';
        $cable->specs = array_replace($cable->specs, ['per_unit_length_m' => 100]);

        $result = app(CableSelector::class)->select('grid', $this->site(), collect([$cable]));

        $this->assertEqualsWithDelta(1, $result['selected']['quantity'], 0.001);
        $this->assertSame('roll', $result['selected']['unit']);
    }

    public function test_selected_cable_replaces_allowances_and_combines_real_circuit_lengths_once(): void
    {
        $this->seed(SolarWorkbookSeeder::class);
        $input = Estimate::where('reference', 'WORKBOOK-EXAMPLE')->sole()->inputs;
        $cables = $this->cables();
        $selected = $cables[1];
        $input['circuits'] = ['grid' => $this->site(), 'backup' => $this->site()];

        $result = app(EstimateCalculator::class)->calculate($input);
        $lines = collect($result['lines']);

        $this->assertCount(1, $lines->where('product_id', $selected->id));
        $this->assertEqualsWithDelta(80, $lines->firstWhere('product_id', $selected->id)['quantity'], 0.001);
        $this->assertNull($lines->firstWhere('sku', 'BOM-44'));
        $this->assertNull($lines->firstWhere('sku', 'BOM-56'));
        $input['overrides'][$selected->id] = ['quantity' => 90];
        $adjusted = collect(app(EstimateCalculator::class)->calculate($input)['lines'])->firstWhere('product_id', $selected->id);
        $this->assertEqualsWithDelta(90, $adjusted['quantity'], 0.001);
    }
}
