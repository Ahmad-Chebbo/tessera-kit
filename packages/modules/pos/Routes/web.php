<?php

use App\Modules\Pos\Http\Controllers\Web\POSController;
use App\Modules\Pos\Http\Controllers\Web\PosShiftController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:access pos')->group(function () {
    Route::get('/pos', [POSController::class, 'index'])->name('pos.index');
    Route::get('/pos/products', [POSController::class, 'products'])->name('pos.products');
    Route::post('/pos/discounts/preview', [POSController::class, 'previewDiscounts'])->name('pos.discounts.preview');
    Route::post('/pos/checkout', [POSController::class, 'checkout'])->name('pos.checkout');
    Route::get('/pos/receipt/{order}', [POSController::class, 'receipt'])->name('pos.receipt');
    Route::post('/pos/sync', [POSController::class, 'syncOfflineSales'])->name('pos.sync');
    Route::post('/pos/hold', [POSController::class, 'holdOrder'])->name('pos.hold');
    Route::get('/pos/held-orders', [POSController::class, 'getHeldOrders'])->name('pos.held-orders');
    Route::get('/pos/held-orders/{heldOrder}', [POSController::class, 'retrieveHeldOrder'])->name('pos.retrieve-held-order');
    Route::delete('/pos/held-orders/{heldOrder}', [POSController::class, 'deleteHeldOrder'])->name('pos.delete-held-order');
});

Route::middleware('module:shifts')->group(function () {
    Route::get('/pos-shifts', [PosShiftController::class, 'index'])->name('pos-shifts.index');
    Route::post('/pos-shifts/open', [PosShiftController::class, 'open'])->name('pos-shifts.open');
    Route::get('/pos-shifts/{posShift}', [PosShiftController::class, 'show'])->name('pos-shifts.show');
    Route::post('/pos-shifts/{posShift}/close', [PosShiftController::class, 'close'])->name('pos-shifts.close');
});
