<?php

namespace App\Services\SolarEstimation;

use Illuminate\Validation\ValidationException;

class SizingCalculator
{
    public function calculate(array $input, array $panel, array $inverter, array $battery): array
    {
        foreach (['panel wattage' => $panel['power_w'] ?? null, 'inverter kW per unit' => $inverter['power_kw'] ?? null] as $label => $value) {
            if (! is_numeric($value) || $value <= 0) {
                throw ValidationException::withMessages(['panel_id' => "Add a positive {$label} in the materials catalog before sizing."]);
            }
        }
        $daily = $input['monthly_kwh'] / 30;
        $night = $daily * $input['night_percent'] / 100;
        $yield = $input['yield_percent'] / 100;
        $panels = $input['panel_count_override'] ?? (int) ceil($daily / ($input['sun_hours'] * $yield * $panel['power_w'] / 1000));
        $batteries = 0;
        $batteryKwh = 0;
        if ($input['battery_enabled'] && $night > 0) {
            if (($battery['voltage_v'] ?? 0) <= 0 || ($battery['capacity_ah'] ?? 0) <= 0) {
                throw ValidationException::withMessages(['battery_id' => 'Add battery voltage and capacity (Ah) in the materials catalog.']);
            }
            $batteryKwh = $battery['voltage_v'] * $battery['capacity_ah'] / 1000;
            $batteries = (int) ceil($night / ($input['dod_percent'] / 100) / $batteryKwh);
        }
        $pv = $panels * $panel['power_w'] / 1000;
        $installedInverter = $inverter['power_kw'] * $input['inverter_count'];
        $monthlyYield = min($pv, $installedInverter) * $input['sun_hours'] * $yield * 30;
        $savings = min($monthlyYield, $input['monthly_kwh']) * $input['self_consumption_percent'] / 100 * $input['electricity_rate'];

        return ['daily_kwh' => $daily, 'night_kwh' => $night, 'energy_target_kwp' => $daily / ($input['sun_hours'] * $yield), 'panel_count' => (int) $panels, 'pv_kwp' => $pv, 'inverter_count' => (int) $input['inverter_count'], 'inverter_kw' => $installedInverter, 'battery_count' => $batteries, 'battery_kwh' => $batteries * $batteryKwh, 'monthly_yield_kwh' => $monthlyYield, 'monthly_savings' => round($savings, 2)];
    }
}
