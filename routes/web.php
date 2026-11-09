<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return to_route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware('auth')->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    require __DIR__.'/modules/solar-estimation.php';
});
