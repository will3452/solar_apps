<?php

namespace App\Services\SolarEstimation;

use App\Models\SolarEstimation\CatalogItem;
use Illuminate\Validation\ValidationException;

class EstimateCalculator
{
    public function __construct(private SizingCalculator $sizing, private DependencyGraph $graph, private CableSelector $cables) {}

    public function calculate(array $input, array $previousSnapshot = []): array
    {
        $items = CatalogItem::with('dependencies')->where('active', true)->get()->keyBy('id');
        $selected = [];
        foreach (['panel', 'inverter', 'battery'] as $role) {
            $item = $items->get($input[$role.'_id'] ?? 0);
            if ($role === 'battery' && ! $input['battery_enabled'] && (! $item || $item->role !== $role)) {
                continue;
            }
            if (! $item || $item->role !== $role) {
                throw ValidationException::withMessages([$role.'_id' => "Select an active {$role} from the materials catalog."]);
            }
            $selected[$role] = $item;
        }
        $sizing = $this->sizing->calculate($input, $selected['panel']->specs ?? [], $selected['inverter']->specs ?? [], $selected['battery']->specs ?? []);
        $roots = [$selected['panel']->id, $selected['inverter']->id];
        if ($sizing['battery_count'] > 0) {
            $roots[] = $selected['battery']->id;
        }
        $roots = array_merge($roots, $items->where('is_system_item', true)->modelKeys());
        $ids = $this->graph->ordered($items, $roots);
        $choices = [];
        foreach (CableSelector::CIRCUITS as $key => $label) {
            if (in_array($key, ['battery', 'backup'], true) && $sizing['battery_count'] === 0) {
                $choices[$key] = ['status' => 'not_needed', 'message' => 'Not included: this estimate has no batteries.', 'missing' => []];

                continue;
            }
            $choices[$key] = $this->cables->select($key, $input['circuits'][$key] ?? [], $items->where('role', 'cable'), $selected['inverter']->specs['circuits'][$key] ?? []);
        }
        $mainCounts = [$selected['panel']->id => $sizing['panel_count'], $selected['inverter']->id => $sizing['inverter_count']];
        if (isset($selected['battery'])) {
            $mainCounts[$selected['battery']->id] = $sizing['battery_count'];
        }
        $oldPrices = collect($previousSnapshot['lines'] ?? [])->keyBy('product_id');
        $lines = [];
        $warnings = [
            'Sizing is an energy estimate, not a peak-load or electrical design. Confirm actual peak loads, product models and battery BMS/discharge limits.',
            'Mounting, protection and connector quantities start from workbook allowances. Confirm roof layout, string voltage/current and matching connector families.',
            'Savings use the stated sun hours, yield and self-consumption assumptions, capped at monthly use. Export credits, financing and degradation are not modeled.',
        ];
        if (isset($input['peak_load_kw']) && $input['peak_load_kw'] > $sizing['inverter_kw']) {
            $warnings[] = 'The entered peak load exceeds the total selected inverter rating.';
        }
        foreach ($selected as $equipment) {
            if ($mainCounts[$equipment->id] > 0 && $equipment->dependencies->isEmpty()) {
                $warnings[] = $equipment->name.': no dependent materials are configured. Add its required materials in the catalog.';
            }
        }
        foreach ($ids as $id) {
            $item = $items[$id];
            if ($item->requires_battery && $sizing['battery_count'] === 0) {
                continue;
            }
            $factor = $item->quantity_factor;
            $grid = $items->firstWhere('sku', 'BOM-44');
            $gridQuantity = $grid ? ($input['overrides'][$grid->id]['quantity'] ?? $grid->default_quantity) : 0;
            $quantity = match ($item->quantity_rule) {
                'panels' => $sizing['panel_count'] * $factor,
                'inverters' => $sizing['inverter_count'] * $factor,
                'batteries' => $sizing['battery_count'] * $factor,
                'panel_groups' => ceil($sizing['panel_count'] / $item->group_size) * $factor,
                'grid_allowance' => $gridQuantity,
                default => $item->default_quantity,
            };
            $isMain = array_key_exists($id, $mainCounts);
            if ($isMain) {
                $quantity = $mainCounts[$id];
            }
            $reason = CatalogItem::quantityRules()[$item->quantity_rule].($item->quantity_rule !== 'fixed' ? ' (factor '.$factor.')' : ' from source / catalog');
            if ($isMain) {
                $reason = 'Equipment count from sizing. Change the equipment quantity in Sizing and site.';
            }
            foreach ($item->dependencies as $dependency) {
                if (! $dependency->active) {
                    $warnings[] = $item->name.' requires '.$dependency->name.', which is inactive and has not been priced.';
                }
            }
            $status = $item->optional ? 'optional' : 'included';
            if ($item->circuit && ($choices[$item->circuit]['status'] ?? null) === 'candidate') {
                $choice = $choices[$item->circuit]['selected'];
                $item = $items[$choice['id']];
                $quantity = $choice['quantity'];
                $reason = CableSelector::CIRCUITS[$items[$id]->circuit].': '.$choice['total_metres'].' m from route × conductors × runs; '.$choice['corrected_ampacity_a'].' A corrected ampacity; '.$choice['drop_percent'].'% resistive drop. Candidate for review.';
            } elseif ($item->circuit) {
                $status = 'incomplete';
                $reason .= '. Provisional source allowance retained; cable selection needs more data.';
            }
            if (in_array($item->sku, ['BOM-43', 'BOM-56'], true) && empty($item->specs['per_unit_length_m'])) {
                $status = 'incomplete';
                $reason .= '. Metres per piece are not specified.';
            }
            $saved = $oldPrices->get($item->id);
            $cost = empty($input['refresh_catalog_prices']) && $saved ? $saved['unit_cost'] : $item->buying_cost;
            if (isset($lines[$item->id])) {
                $lines[$item->id]['quantity'] += $quantity;
                $lines[$item->id]['reason'] .= ' '.$reason;
            } else {
                $lines[$item->id] = ['product_id' => $item->id, 'adjustment_id' => $item->id, 'is_main' => $isMain, 'sku' => $item->sku, 'name' => $item->name, 'category' => $item->category, 'quantity' => $quantity, 'unit' => $item->unit, 'unit_cost' => $cost === null ? null : (float) $cost, 'status' => $status, 'reason' => $reason, 'source' => $item->source, 'specs' => $item->specs, 'specification_status' => $item->specification_status];
            }
        }
        foreach ($input['additional_works'] ?? [] as $index => $work) {
            if (empty($work['description']) || ($work['quantity'] ?? 0) <= 0) {
                continue;
            }
            if (collect($lines)->contains(fn (array $line): bool => $line['quantity'] > 0 && mb_strtolower(trim($line['name'])) === mb_strtolower(trim($work['description'])))) {
                throw ValidationException::withMessages(['additional_works' => $work['description'].' is already included. Keep the work in one place to avoid charging twice.']);
            }
            $lines['additional-'.$index] = ['product_id' => null, 'adjustment_id' => null, 'sku' => 'ADDITIONAL', 'name' => $work['description'], 'category' => 'Additional works', 'quantity' => (float) $work['quantity'], 'unit' => $work['unit'] ?: 'lot', 'unit_cost' => (float) $work['unit_cost'], 'status' => 'included', 'reason' => 'Additional work entered for this estimate.', 'source' => null, 'specs' => [], 'specification_status' => 'estimate_allowance'];
        }
        $buying = 0;
        $selling = 0;
        foreach ($lines as &$line) {
            $override = $line['product_id'] ? ($input['overrides'][$line['product_id']] ?? []) : [];
            if (($line['is_main'] ?? false) && ((! empty($override['exclude'])) || (isset($override['quantity']) && $override['quantity'] !== '' && (float) $override['quantity'] !== (float) $line['quantity']))) {
                throw ValidationException::withMessages(['overrides' => 'Change main equipment quantities in Sizing and site so dependent materials and sizing stay consistent.']);
            }
            if (($override['quantity'] ?? null) !== null && $override['quantity'] !== '') {
                $line['quantity'] = (float) $override['quantity'];
                $line['reason'] .= '. Quantity adjusted for this estimate.';
            }
            if (! empty($override['exclude'])) {
                $line['quantity'] = 0;
                $line['status'] = 'excluded';
                $line['reason'] .= '. Excluded for this estimate.';
            }
            if (($override['unit_cost'] ?? null) !== null && $override['unit_cost'] !== '') {
                $line['unit_cost'] = (float) $override['unit_cost'];
            }
            if (! empty($override['note'])) {
                $line['reason'] .= ' '.$override['note'];
            }
            if ($line['unit_cost'] === null && $line['quantity'] > 0) {
                $line['status'] = 'incomplete';
                $warnings[] = $line['name'].': buying price is missing. Totals include known costs only.';
            }
            $line['quantity'] = round($line['quantity'], 3);
            $extended = $line['quantity'] * ($line['unit_cost'] ?? 0) * 100;
            if (! is_finite($extended) || $extended > 100000000000000) {
                throw ValidationException::withMessages(['overrides' => 'A calculated line amount is too large. Check its quantity, unit and buying price.']);
            }
            $line['buying_cents'] = (int) round($line['quantity'] * ($line['unit_cost'] ?? 0) * 100);
            $line['selling_cents'] = (int) round($line['buying_cents'] / (1 - $input['margin_percent'] / 100));
            $buying += $line['buying_cents'];
            $selling += $line['selling_cents'];
        }
        unset($line);
        $discount = (int) round($input['discount_amount'] * 100);
        if ($discount > $selling) {
            throw ValidationException::withMessages(['discount_amount' => 'The discount cannot exceed the selling subtotal.']);
        }
        $tax = (int) round(($selling - $discount) * $input['tax_percent'] / 100);
        $total = $selling - $discount + $tax;
        $sizing['payback_months'] = $sizing['monthly_savings'] > 0 ? round($total / 100 / $sizing['monthly_savings'], 1) : null;

        return ['version' => 1, 'calculated_at' => now()->toIso8601String(), 'sizing' => $sizing, 'lines' => array_values($lines), 'cables' => $choices, 'warnings' => $warnings, 'pricing' => ['buying_cents' => $buying, 'selling_cents' => $selling, 'discount_cents' => $discount, 'tax_cents' => $tax, 'total_cents' => $total, 'gross_profit_cents' => $selling - $discount - $buying, 'margin_percent' => $input['margin_percent'], 'tax_percent' => $input['tax_percent']], 'assumptions' => ['sun_hours' => $input['sun_hours'], 'yield_percent' => $input['yield_percent'], 'self_consumption_percent' => $input['self_consumption_percent'], 'dod_percent' => $input['dod_percent'], 'electricity_rate' => $input['electricity_rate'], 'pricing_method' => 'Each line buying cost ÷ (1 − gross margin), discount before configured tax. Rounded to cents per line. Tax is a quote setting, not a legal determination.'], 'equipment' => collect($selected)->map(fn ($item): array => ['name' => $item->name, 'specs' => $item->specs, 'source' => $item->source, 'status' => $item->specification_status])->all()];
    }
}
