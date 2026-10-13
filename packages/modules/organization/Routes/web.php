<?php

use App\Modules\Organization\Http\Controllers\Web\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:view organization')->group(function () {
    Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');
});
Route::middleware('can:manage organization')->group(function () {
    Route::post('/organization/departments', [OrganizationController::class, 'storeDepartment'])->name('organization.departments.store');
    Route::put('/organization/departments/{department}', [OrganizationController::class, 'updateDepartment'])->name('organization.departments.update');
    Route::delete('/organization/departments/{department}', [OrganizationController::class, 'destroyDepartment'])->name('organization.departments.destroy');
    Route::post('/organization/positions', [OrganizationController::class, 'storePosition'])->name('organization.positions.store');
    Route::put('/organization/positions/{position}', [OrganizationController::class, 'updatePosition'])->name('organization.positions.update');
    Route::delete('/organization/positions/{position}', [OrganizationController::class, 'destroyPosition'])->name('organization.positions.destroy');
    Route::put('/organization/employees/{employee}', [OrganizationController::class, 'assign'])->name('organization.employees.assign');
});
