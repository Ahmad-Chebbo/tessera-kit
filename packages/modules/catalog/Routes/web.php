<?php

use App\Modules\Catalog\Http\Controllers\Web\CategoryController;
use App\Modules\Catalog\Http\Controllers\Web\ProductController;
use App\Modules\Catalog\Http\Controllers\Web\UnitController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:view products')->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
});
Route::middleware('can:create products')->group(function () {
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
});
Route::middleware('can:edit products')->group(function () {
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
});
Route::delete('/products/{product}', [ProductController::class, 'destroy'])
    ->middleware('can:delete products')
    ->name('products.destroy');

Route::middleware('can:view categories')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
});
Route::middleware('can:create categories')->group(function () {
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
});
Route::middleware('can:edit categories')->group(function () {
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
});
Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
    ->middleware('can:delete categories')
    ->name('categories.destroy');

Route::middleware('can:manage settings')->group(function () {
    // Units Routes
    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
});
