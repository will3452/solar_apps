<?php

namespace Database\Seeders;

use App\Models\SolarEstimation\CatalogItem;
use App\Models\SolarEstimation\Estimate;
use App\Services\SolarEstimation\EstimateCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SolarWorkbookSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $data = json_decode(file_get_contents(database_path('seeders/data/solar-workbook.json')), true, flags: JSON_THROW_ON_ERROR);
            $created = [];
            foreach ($data['items'] as $row) {
                $parent = $row['parent_sku'];
                unset($row['parent_sku']);
                $row['source']['sha256'] = $data['source']['sha256'];
                $row['specification_status'] = 'source_unverified';
                $item = CatalogItem::firstOrCreate(['sku' => $row['sku']], $row);
                if ($item->wasRecentlyCreated) {
                    $created[$item->id] = $parent;
                }
            }
            foreach ($created as $id => $parent) {
                if ($parent) {
                    CatalogItem::where('sku', $parent)->firstOrFail()->dependencies()->syncWithoutDetaching([$id]);
                }
            }
            if (! Estimate::where('reference', 'WORKBOOK-EXAMPLE')->exists()) {
                $input = array_merge(config('solar_estimation.defaults'), $data['defaults'], [
                    'panel_id' => CatalogItem::where('sku', 'BOM-24')->sole()->id,
                    'inverter_id' => CatalogItem::where('sku', 'BOM-25')->sole()->id,
                    'battery_id' => CatalogItem::where('sku', 'BOM-26')->sole()->id,
                    'customer_name' => 'Workbook example', 'address' => '', 'circuits' => [], 'overrides' => [], 'additional_works' => [],
                    'notes' => 'Example based on the supplied workbook. It preserves three physical inverters as entered in BOM H25. Source product identities, protection ratings, routes and unit lengths need confirmation.',
                ]);
                Estimate::create(['reference' => 'WORKBOOK-EXAMPLE', 'customer_name' => $input['customer_name'], 'address' => '', 'inputs' => $input, 'snapshot' => app(EstimateCalculator::class)->calculate($input)]);
            }
        });
    }
}
