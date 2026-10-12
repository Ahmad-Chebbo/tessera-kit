<?php

use App\Modules\Expenses\Http\Controllers\Web\ExpenseCategoryController;
use App\Modules\Expenses\Http\Controllers\Web\ExpenseController;
use App\Modules\Expenses\Http\Controllers\Web\ExpenseReportController;
use App\Modules\Expenses\Http\Controllers\Web\RecurringExpenseController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:view expenses')->group(function () {
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/report', [ExpenseReportController::class, 'report'])->name('expenses.report');
    Route::get('/expenses/statement', [ExpenseReportController::class, 'statement'])->name('expenses.statement');
    Route::get('/expenses/categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
    Route::get('/expenses/recurring', [RecurringExpenseController::class, 'index'])->name('recurring-expenses.index');
    Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->whereNumber('expense')->name('expenses.receipt');
});

Route::middleware('can:create expenses')->group(function () {
    Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
});

Route::middleware('can:edit expenses')->group(function () {
    Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->whereNumber('expense')->name('expenses.edit');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->whereNumber('expense')->name('expenses.update');

    Route::post('/expenses/categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
    Route::put('/expenses/categories/{category}', [ExpenseCategoryController::class, 'update'])->whereNumber('category')->name('expense-categories.update');
    Route::delete('/expenses/categories/{category}', [ExpenseCategoryController::class, 'destroy'])->whereNumber('category')->name('expense-categories.destroy');

    Route::post('/expenses/recurring', [RecurringExpenseController::class, 'store'])->name('recurring-expenses.store');
    Route::put('/expenses/recurring/{recurring}', [RecurringExpenseController::class, 'update'])->whereNumber('recurring')->name('recurring-expenses.update');
    Route::post('/expenses/recurring/{recurring}/toggle', [RecurringExpenseController::class, 'toggle'])->whereNumber('recurring')->name('recurring-expenses.toggle');
    Route::delete('/expenses/recurring/{recurring}', [RecurringExpenseController::class, 'destroy'])->whereNumber('recurring')->name('recurring-expenses.destroy');
});

Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('can:delete expenses')->whereNumber('expense')->name('expenses.destroy');
