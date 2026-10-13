<?php

use App\Modules\Tenant\Http\Controllers\Web\BranchController;
use App\Modules\Tenant\Http\Controllers\Web\ExchangeRateController;
use App\Modules\Tenant\Http\Controllers\Web\LocationController;
use App\Modules\Tenant\Http\Controllers\Web\SettingsController;
use Illuminate\Support\Facades\Route;
use Tessera\Engine\ModuleRegistry;

$holdsStock = app(ModuleRegistry::class)->has('inventory') || app(ModuleRegistry::class)->has('pos');

Route::middleware('can:manage settings')->group(function () {
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
});

Route::middleware('can:manage branches')->group(function () {
    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
    Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
});

if ($holdsStock) {
    Route::middleware('can:manage settings')->group(function () {
        Route::get('/exchange-rates', [ExchangeRateController::class, 'index'])->name('exchange-rates.index');
        Route::post('/exchange-rates', [ExchangeRateController::class, 'store'])->name('exchange-rates.store');
        Route::delete('/exchange-rates/{exchangeRate}', [ExchangeRateController::class, 'destroy'])->name('exchange-rates.destroy');
    });

    Route::middleware('can:manage locations')->group(function () {
        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::get('/locations/create', [LocationController::class, 'create'])->name('locations.create');
        Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
        Route::get('/locations/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit');
        Route::put('/locations/{location}', [LocationController::class, 'update'])->name('locations.update');
        Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');
    });
}
