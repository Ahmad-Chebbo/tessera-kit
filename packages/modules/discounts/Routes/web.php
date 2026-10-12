<?php

use App\Modules\Discounts\Http\Controllers\Web\DiscountRuleController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage discounts')->prefix('discounts')->name('discounts.')->group(function () {
    Route::get('/', [DiscountRuleController::class, 'index'])->name('index');
    Route::get('/create', [DiscountRuleController::class, 'create'])->name('create');
    Route::post('/', [DiscountRuleController::class, 'store'])->name('store');
    Route::get('/simulator', [DiscountRuleController::class, 'simulator'])->name('simulator');
    Route::post('/simulate', [DiscountRuleController::class, 'simulate'])->name('simulate');
    Route::get('/{rule}/edit', [DiscountRuleController::class, 'edit'])->whereNumber('rule')->name('edit');
    Route::put('/{rule}', [DiscountRuleController::class, 'update'])->whereNumber('rule')->name('update');
    Route::delete('/{rule}', [DiscountRuleController::class, 'destroy'])->whereNumber('rule')->name('destroy');
    Route::post('/templates/{template}', [DiscountRuleController::class, 'storeFromTemplate'])->where('template', '[a-z_]+')->name('from-template');
    Route::post('/{rule}/toggle', [DiscountRuleController::class, 'toggle'])->whereNumber('rule')->name('toggle');
    Route::post('/{rule}/duplicate', [DiscountRuleController::class, 'duplicate'])->whereNumber('rule')->name('duplicate');
});
