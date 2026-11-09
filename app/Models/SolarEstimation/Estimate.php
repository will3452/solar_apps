<?php

namespace App\Models\SolarEstimation;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reference', 'customer_name', 'address', 'inputs', 'snapshot', 'created_by'])]
class Estimate extends Model
{
    protected $table = 'solar_estimates';

    protected function casts(): array
    {
        return ['inputs' => 'array', 'snapshot' => 'array'];
    }
}
