<?php

use App\Http\Controllers\SolarEstimation\CatalogController;
use App\Http\Controllers\SolarEstimation\EstimateController;
use Illuminate\Support\Facades\Route;

Route::prefix('modules/solar-estimation')->name('modules.solar-estimation.')->group(function (): void {
    Route::get('/', [EstimateController::class, 'index'])->name('index');
    Route::post('/estimates/preview', [EstimateController::class, 'preview'])->name('estimates.preview');
    Route::post('/estimates/{estimate}/preview', [EstimateController::class, 'preview'])->name('estimates.preview-saved');
    Route::get('/estimates/{estimate}/proposal', [EstimateController::class, 'proposal'])->name('estimates.proposal');
    Route::resource('estimates', EstimateController::class)->except(['index', 'destroy']);
    Route::resource('catalog', CatalogController::class)->parameters(['catalog' => 'catalog_item'])->except(['show', 'destroy']);
});
