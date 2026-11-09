<?php

namespace App\Http\Requests\SolarEstimation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['battery_enabled' => $this->boolean('battery_enabled'), 'refresh_catalog_prices' => $this->boolean('refresh_catalog_prices')]);
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:2000'],
            'monthly_kwh' => ['required', 'numeric', 'between:0,100000000'], 'night_percent' => ['required', 'numeric', 'between:0,100'],
            'panel_id' => ['required', 'integer', 'exists:solar_catalog_items,id'], 'inverter_id' => ['required', 'integer', 'exists:solar_catalog_items,id'], 'battery_id' => [Rule::requiredIf(fn (): bool => $this->boolean('battery_enabled')), 'nullable', 'integer', 'exists:solar_catalog_items,id'],
            'inverter_count' => ['required', 'integer', 'between:1,100'], 'battery_enabled' => ['boolean'], 'panel_count_override' => ['nullable', 'integer', 'between:0,100000'], 'peak_load_kw' => ['nullable', 'numeric', 'between:0,100000'],
            'sun_hours' => ['required', 'numeric', 'between:0.1,24'], 'yield_percent' => ['required', 'numeric', 'between:0.1,100'], 'dod_percent' => ['required', 'numeric', 'between:0.1,100'],
            'self_consumption_percent' => ['required', 'numeric', 'between:0,100'], 'electricity_rate' => ['required', 'numeric', 'between:0,1000'],
            'margin_percent' => ['required', 'numeric', 'between:0,95'], 'tax_percent' => ['required', 'numeric', 'between:0,100'], 'discount_amount' => ['required', 'numeric', 'between:0,1000000000000'],
            'notes' => ['nullable', 'string', 'max:10000'], 'terms' => ['nullable', 'string', 'max:10000'], 'refresh_catalog_prices' => ['boolean'],
            'overrides' => ['nullable', 'array', 'max:500'], 'overrides.*' => ['array:quantity,unit_cost,exclude,note'], 'overrides.*.quantity' => ['nullable', 'numeric', 'between:0,10000000'], 'overrides.*.unit_cost' => ['nullable', 'numeric', 'between:0,1000000000'], 'overrides.*.exclude' => ['nullable', 'boolean'], 'overrides.*.note' => ['nullable', 'string', 'max:500'],
            'additional_works' => ['nullable', 'array', 'max:20'], 'additional_works.*' => ['array:description,quantity,unit,unit_cost'], 'additional_works.*.description' => ['nullable', 'string', 'max:255'], 'additional_works.*.quantity' => ['nullable', 'numeric', 'between:0,1000000'], 'additional_works.*.unit' => ['nullable', 'string', 'max:40'], 'additional_works.*.unit_cost' => ['nullable', 'numeric', 'between:0,1000000000'],
            'circuits' => ['nullable', 'array:pv,grid,backup,battery'], 'circuits.*' => ['array:length_m,current_a,voltage_v,protection_a,temperature_factor,grouping_factor,installation_factor,terminal_min_mm2,terminal_max_mm2,max_drop_percent,conductors,runs,phase,basis'],
            'circuits.*.length_m' => ['nullable', 'numeric', 'between:0.01,100000'], 'circuits.*.current_a' => ['nullable', 'numeric', 'between:0.01,100000'], 'circuits.*.voltage_v' => ['nullable', 'numeric', 'between:0.01,100000'], 'circuits.*.protection_a' => ['nullable', 'numeric', 'between:0.01,100000'],
            'circuits.*.temperature_factor' => ['nullable', 'numeric', 'between:0.01,1'], 'circuits.*.grouping_factor' => ['nullable', 'numeric', 'between:0.01,1'], 'circuits.*.installation_factor' => ['nullable', 'numeric', 'between:0.01,1'],
            'circuits.*.terminal_min_mm2' => ['nullable', 'numeric', 'between:0.01,10000'], 'circuits.*.terminal_max_mm2' => ['nullable', 'numeric', 'between:0.01,10000'], 'circuits.*.max_drop_percent' => ['nullable', 'numeric', 'between:0.01,20'], 'circuits.*.conductors' => ['nullable', 'integer', 'between:1,12'], 'circuits.*.runs' => ['nullable', 'integer', 'between:1,1000'], 'circuits.*.phase' => ['nullable', 'in:dc,single,three'], 'circuits.*.basis' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $works = $this->input('additional_works', []);
            if (! is_array($works)) {
                return;
            }
            foreach ($works as $index => $work) {
                if (! is_array($work)) {
                    continue;
                }
                if (($work['quantity'] ?? 0) > 0 && (empty($work['description']) || ! isset($work['unit_cost']))) {
                    $validator->errors()->add("additional_works.{$index}.description", 'Enter a description and a buying price for each additional work item.');
                }
            }
        }];
    }
}
