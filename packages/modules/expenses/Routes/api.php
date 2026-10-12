<?php

use App\Modules\Expenses\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Modules\Expenses\Http\Controllers\Api\V1\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:view expenses')->group(function () {
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->whereNumber('expense')->name('expenses.show');
    Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->whereNumber('expense')->name('expenses.receipt');
});

Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('can:create expenses')->name('expenses.store');
Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->middleware('can:edit expenses')->whereNumber('expense')->name('expenses.update');
Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('can:delete expenses')->whereNumber('expense')->name('expenses.destroy');
