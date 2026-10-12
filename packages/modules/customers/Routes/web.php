<?php

use App\Modules\Customers\Http\Controllers\Web\CustomerController;
use App\Modules\Customers\Http\Controllers\Web\CustomerGroupController;
use Illuminate\Support\Facades\Route;

Route::get('/customers', [CustomerController::class, 'index'])->middleware('can:view customers')->name('customers.index');
Route::get('/customers/create', [CustomerController::class, 'create'])->middleware('can:create customers')->name('customers.create');
Route::post('/customers', [CustomerController::class, 'store'])->middleware('can:create customers')->name('customers.store');
Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('can:view customers')->name('customers.show');
Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->middleware('can:edit customers')->name('customers.edit');
Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('can:edit customers')->name('customers.update');
Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('can:delete customers')->name('customers.destroy');

Route::middleware('can:edit customers')->group(function () {
    Route::get('/customer-groups', [CustomerGroupController::class, 'index'])->name('customer-groups.index');
    Route::post('/customer-groups', [CustomerGroupController::class, 'store'])->name('customer-groups.store');
    Route::put('/customer-groups/{group}', [CustomerGroupController::class, 'update'])->name('customer-groups.update');
    Route::delete('/customer-groups/{group}', [CustomerGroupController::class, 'destroy'])->name('customer-groups.destroy');
});
