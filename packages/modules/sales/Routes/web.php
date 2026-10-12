<?php

use App\Modules\Sales\Http\Controllers\Web\OrderController;
use App\Modules\Sales\Http\Controllers\Web\POSController;
use App\Modules\Sales\Http\Controllers\Web\PosShiftController;
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

Route::middleware('can:view orders')->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});
Route::post('/orders/{order}/void', [OrderController::class, 'void'])
    ->middleware('can:void orders')
    ->name('orders.void');
Route::post('/orders/{order}/mark-paid', [OrderController::class, 'markAsPaid'])
    ->middleware('can:void orders')
    ->name('orders.mark-paid');

Route::middleware('module:shifts')->group(function () {
    Route::get('/pos-shifts', [PosShiftController::class, 'index'])->name('pos-shifts.index');
    Route::post('/pos-shifts/open', [PosShiftController::class, 'open'])->name('pos-shifts.open');
    Route::get('/pos-shifts/{posShift}', [PosShiftController::class, 'show'])->name('pos-shifts.show');
    Route::post('/pos-shifts/{posShift}/close', [PosShiftController::class, 'close'])->name('pos-shifts.close');
});
