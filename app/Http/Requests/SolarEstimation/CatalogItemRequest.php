<?php

namespace App\Http\Requests\SolarEstimation;

use App\Models\SolarEstimation\CatalogItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogItemRequest extends FormRequest
{
    public const SPEC_FIELDS = ['power_w' => 'Panel rating (W)', 'power_kw' => 'Inverter rating per unit (kW)', 'voltage_v' => 'Battery nominal voltage (V)', 'capacity_ah' => 'Battery capacity (Ah)', 'section_mm2' => 'Cable cross-section (mm²)', 'ampacity_a' => 'Documented base ampacity (A)', 'resistance_ohm_per_km' => 'Resistance at design temperature (Ω/km)', 'voltage_rating_v' => 'Cable voltage rating (V)', 'per_unit_length_m' => 'Metres per selling unit'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $flags = [];
        foreach (['active', 'is_system_item', 'requires_battery', 'optional'] as $key) {
            $flags[$key] = $this->boolean($key);
        }
        $raw = $this->input('specs_json', '{}') ?: '{}';
        $specs = is_string($raw) && json_validate($raw) ? json_decode($raw, true) : [];
        if (! is_array($specs)) {
            $specs = [];
        }
        foreach (self::SPEC_FIELDS as $key => $label) {
            $specs[$key] = $this->input('spec_'.$key);
        }
        $specs['provenance'] = $this->input('spec_provenance');
        $specs['allowed_circuits'] = $this->input('allowed_circuits', []);
        $this->merge(array_merge($flags, ['specs' => $specs]));
    }

    public function rules(): array
    {
        $rules = [
            'sku' => ['required', 'string', 'max:80', Rule::unique('solar_catalog_items')->ignore($this->route('catalog_item'))],
            'name' => ['required', 'string', 'max:255'], 'category' => ['required', 'string', 'max:255'], 'unit' => ['required', 'string', 'max:40'], 'buying_cost' => ['nullable', 'numeric', 'between:0,1000000000'],
            'role' => ['required', 'in:panel,inverter,battery,material,labor,cable'], 'quantity_rule' => ['required', Rule::in(array_keys(CatalogItem::quantityRules()))], 'quantity_factor' => ['required', 'numeric', 'between:0,100000'], 'default_quantity' => ['required', 'numeric', 'between:0,10000000'], 'group_size' => ['required', 'integer', 'between:1,100000'],
            'active' => ['boolean'], 'is_system_item' => ['boolean'], 'requires_battery' => ['boolean'], 'optional' => ['boolean'], 'circuit' => ['nullable', 'in:pv,grid,backup,battery'], 'specification_status' => ['required', 'in:source_unverified,reviewed'],
            'notes' => ['nullable', 'string', 'max:10000'], 'specs_json' => ['nullable', 'json'], 'specs' => ['array'], 'specs.provenance' => ['nullable', 'string', 'max:2000'], 'specs.allowed_circuits' => ['array'], 'specs.allowed_circuits.*' => ['in:pv,grid,backup,battery'], 'specs.circuits' => ['sometimes', 'array'],
            'dependencies' => ['nullable', 'array', 'max:500'], 'dependencies.*' => ['integer', 'distinct', 'exists:solar_catalog_items,id'],
        ];
        foreach (self::SPEC_FIELDS as $key => $label) {
            $rules['specs.'.$key] = ['nullable', 'numeric', 'gt:0', 'max:1000000000'];
        }
        foreach (['pv', 'grid', 'backup', 'battery'] as $circuit) {
            $rules['specs.circuits.'.$circuit] = ['sometimes', 'array'];
            $rules['specs.circuits.'.$circuit.'.phase'] = ['sometimes', 'in:dc,single,three'];
            $rules['specs.circuits.'.$circuit.'.basis'] = ['sometimes', 'nullable', 'string', 'max:1000'];
            foreach (['length_m', 'current_a', 'voltage_v', 'protection_a', 'terminal_min_mm2', 'terminal_max_mm2', 'maximum_current_a', 'maximum_protection_a', 'temperature_factor', 'grouping_factor', 'installation_factor', 'max_drop_percent', 'conductors', 'runs'] as $field) {
                $rules['specs.circuits.'.$circuit.'.'.$field] = ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:1000000'];
            }
        }

        foreach (['pv', 'grid', 'backup', 'battery'] as $circuit) {
            foreach (['temperature_factor', 'grouping_factor', 'installation_factor'] as $factor) {
                $rules['specs.circuits.'.$circuit.'.'.$factor] = ['sometimes', 'nullable', 'numeric', 'between:0.01,1'];
            }
            foreach (['conductors', 'runs'] as $count) {
                $rules['specs.circuits.'.$circuit.'.'.$count] = ['sometimes', 'nullable', 'integer', 'between:1,1000'];
            }
        }

        return $rules;
    }
}
