<?php

use App\Modules\Subscription\Http\Controllers\Web\PlanController;
use App\Modules\Subscription\Http\Controllers\Web\SubscriptionController;
use App\Modules\Subscription\Http\Controllers\Web\TenantSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
Route::get('/subscription/expired', [SubscriptionController::class, 'expired'])->name('subscription.expired');

Route::prefix('admin')->name('admin.')->middleware('can:manage tenants')->group(function () {
    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->whereNumber('plan')->name('plans.edit');
    Route::put('/plans/{plan}', [PlanController::class, 'update'])->whereNumber('plan')->name('plans.update');
    Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->whereNumber('plan')->name('plans.destroy');
    Route::get('/tenants/{tenant}/subscription', [TenantSubscriptionController::class, 'edit'])->whereNumber('tenant')->name('tenants.subscription.edit');
    Route::put('/tenants/{tenant}/subscription', [TenantSubscriptionController::class, 'update'])->whereNumber('tenant')->name('tenants.subscription.update');
});
