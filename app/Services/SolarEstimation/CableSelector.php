<?php

namespace App\Services\SolarEstimation;

use Illuminate\Support\Collection;

class CableSelector
{
    public const CIRCUITS = ['pv' => 'PV cable', 'grid' => 'Grid cable', 'backup' => 'Backup-load cable', 'battery' => 'Battery cable'];

    public const FIELDS = [
        'length_m' => 'One-way route length (m)', 'current_a' => 'Design current (A)', 'voltage_v' => 'Circuit voltage (V)',
        'protection_a' => 'Protective-device rating (A)', 'temperature_factor' => 'Temperature correction factor',
        'grouping_factor' => 'Grouping correction factor', 'installation_factor' => 'Installation correction factor',
        'terminal_min_mm2' => 'Smallest permitted terminal cable (mm²)', 'terminal_max_mm2' => 'Largest permitted terminal cable (mm²)',
        'max_drop_percent' => 'Maximum voltage drop (%)', 'conductors' => 'Conductors per run', 'runs' => 'Number of runs',
    ];

    public function select(string $circuit, array $site, Collection $cables, array $productCircuit = []): array
    {
        $data = array_replace($productCircuit, array_filter($site, fn ($value): bool => $value !== null && $value !== ''));
        $missing = [];
        foreach (self::FIELDS as $key => $label) {
            if (! isset($data[$key]) || ! is_numeric($data[$key]) || $data[$key] <= 0) {
                $missing[] = $label;
            }
        }
        if (! in_array($data['phase'] ?? '', ['dc', 'single', 'three'], true)) {
            $missing[] = 'Circuit type (DC, single-phase or three-phase)';
        }
        if (empty($data['basis'])) {
            $missing[] = 'Product manual / electrical-design reference for the circuit values';
        }
        if ($missing) {
            return ['status' => 'incomplete', 'missing' => $missing, 'message' => 'Add the missing circuit details to select a cable.', 'basis' => $data];
        }
        if ($data['terminal_min_mm2'] > $data['terminal_max_mm2'] || $data['protection_a'] < $data['current_a']) {
            return ['status' => 'no_match', 'missing' => [], 'message' => 'Check terminal limits and protective-device rating: the protection must carry the design current.', 'basis' => $data];
        }
        if ((isset($data['maximum_current_a']) && $data['current_a'] > $data['maximum_current_a']) || (isset($data['maximum_protection_a']) && $data['protection_a'] > $data['maximum_protection_a'])) {
            return ['status' => 'no_match', 'missing' => [], 'message' => 'The circuit exceeds a documented equipment current or protection limit.', 'basis' => $data];
        }
        $matches = [];
        $rejected = [];
        $documented = 0;
        foreach ($cables as $cable) {
            $spec = $cable->specs ?? [];
            if (! in_array($circuit, $spec['allowed_circuits'] ?? [], true)) {
                continue;
            }
            $required = ['section_mm2', 'ampacity_a', 'resistance_ohm_per_km', 'voltage_rating_v', 'per_unit_length_m'];
            if ($cable->specification_status !== 'reviewed' || empty($spec['provenance']) || collect($required)->contains(fn ($key): bool => ! isset($spec[$key]) || ! is_numeric($spec[$key]) || $spec[$key] <= 0)) {
                continue;
            }
            $documented++;
            $ampacity = $spec['ampacity_a'] * $data['temperature_factor'] * $data['grouping_factor'] * $data['installation_factor'];
            $factor = $data['phase'] === 'three' ? sqrt(3) : 2;
            $drop = $factor * $data['length_m'] / 1000 * $spec['resistance_ohm_per_km'] * $data['current_a'] / $data['voltage_v'] * 100;
            $reasons = [];
            if ($ampacity < $data['protection_a']) {
                $reasons[] = 'corrected ampacity is below protection';
            }
            if ($drop > $data['max_drop_percent']) {
                $reasons[] = 'voltage drop exceeds the limit';
            }
            if ($spec['voltage_rating_v'] < $data['voltage_v']) {
                $reasons[] = 'cable voltage rating is too low';
            }
            if ($spec['section_mm2'] < $data['terminal_min_mm2'] || $spec['section_mm2'] > $data['terminal_max_mm2']) {
                $reasons[] = 'cable does not fit the documented terminal range';
            }
            if ($reasons) {
                $rejected[] = $cable->name.': '.implode(', ', $reasons).'.';

                continue;
            }
            $metres = $data['length_m'] * $data['conductors'] * $data['runs'];
            $quantity = $metres / $spec['per_unit_length_m'];
            if ($cable->unit !== 'm') {
                $quantity = ceil($quantity);
            }
            $matches[] = ['id' => $cable->id, 'name' => $cable->name, 'quantity' => round($quantity, 3), 'unit' => $cable->unit, 'section_mm2' => $spec['section_mm2'], 'corrected_ampacity_a' => round($ampacity, 2), 'drop_percent' => round($drop, 3), 'total_metres' => $metres, 'provenance' => $spec['provenance']];
        }
        if (! $matches) {
            return ['status' => $documented ? 'no_match' : 'incomplete', 'missing' => $documented ? [] : ['Reviewed cable ampacity, resistance at design temperature, voltage rating, length per sale unit, and source in the catalog'], 'message' => $documented ? 'No documented cable passes all configured checks.' : 'Complete and review cable specifications in the materials catalog.', 'rejected' => $rejected, 'basis' => $data];
        }
        usort($matches, fn ($a, $b): int => ($a['section_mm2'] <=> $b['section_mm2']) ?: ($a['id'] <=> $b['id']));
        $selected = $matches[0];

        return ['status' => 'candidate', 'missing' => [], 'selected' => $selected, 'basis' => $data, 'message' => 'Smallest documented cross-section passing current, corrected ampacity, resistive voltage drop, voltage rating and terminal checks. Design review is still required.'];
    }
}
