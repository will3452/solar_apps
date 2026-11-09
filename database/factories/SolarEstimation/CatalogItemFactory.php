<?php

namespace Database\Factories\SolarEstimation;

use App\Models\SolarEstimation\CatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CatalogItem> */
class CatalogItemFactory extends Factory
{
    protected $model = CatalogItem::class;

    public function definition(): array
    {
        return ['sku' => fake()->unique()->bothify('TEST-####-????'), 'name' => 'Test material', 'category' => 'Test data', 'unit' => 'pcs', 'buying_cost' => 100, 'role' => 'material', 'quantity_rule' => 'fixed', 'default_quantity' => 1, 'quantity_factor' => 1, 'active' => true, 'specs' => []];
    }
}
