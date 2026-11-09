<?php

namespace App\Models\SolarEstimation;

use Database\Factories\SolarEstimation\CatalogItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['sku', 'name', 'category', 'unit', 'buying_cost', 'role', 'quantity_rule', 'quantity_factor', 'group_size', 'default_quantity', 'is_system_item', 'requires_battery', 'optional', 'active', 'circuit', 'specification_status', 'specs', 'source', 'notes'])]
class CatalogItem extends Model
{
    /** @use HasFactory<CatalogItemFactory> */
    use HasFactory;

    protected $table = 'solar_catalog_items';

    protected function casts(): array
    {
        return ['buying_cost' => 'decimal:2', 'quantity_factor' => 'float', 'default_quantity' => 'float', 'specs' => 'array', 'source' => 'array', 'is_system_item' => 'boolean', 'requires_battery' => 'boolean', 'optional' => 'boolean', 'active' => 'boolean'];
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'solar_catalog_dependencies', 'parent_id', 'child_id');
    }

    public static function quantityRules(): array
    {
        return ['fixed' => 'Fixed site allowance', 'panels' => 'Panel count × factor', 'inverters' => 'Physical inverter count × factor', 'batteries' => 'Battery count × factor', 'panel_groups' => 'Rounded-up panel groups × factor', 'grid_allowance' => 'Same quantity as the grid cable allowance'];
    }
}
