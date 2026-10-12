<?php

use App\Modules\Inventory\Http\Controllers\Web\InventoryController;
use App\Modules\Inventory\Http\Controllers\Web\PurchaseController;
use App\Modules\Inventory\Http\Controllers\Web\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:view inventory')->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
});
Route::middleware('can:adjust inventory')->group(function () {
    Route::get('/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    Route::post('/inventory/adjust', [InventoryController::class, 'storeAdjustment'])->name('inventory.storeAdjustment');
});
Route::middleware('can:transfer stock')->group(function () {
    Route::get('/inventory/transfer', [InventoryController::class, 'transfer'])->name('inventory.transfer');
    Route::post('/inventory/transfer', [InventoryController::class, 'storeTransfer'])->name('inventory.storeTransfer');
});

Route::middleware('can:view suppliers')->group(function () {
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
});
Route::middleware('can:create suppliers')->group(function () {
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
});
Route::middleware('can:edit suppliers')->group(function () {
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
});
Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])
    ->middleware('can:delete suppliers')
    ->name('suppliers.destroy');

Route::middleware('can:view purchases')->group(function () {
    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
});
Route::middleware('can:create purchases')->group(function () {
    Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
});
Route::post('/purchases/{purchase}/receive', [PurchaseController::class, 'receive'])
    ->middleware('can:receive purchases')
    ->name('purchases.receive');
