<?php

use App\Modules\Team\Http\Controllers\Web\ActivityLogController;
use App\Modules\Team\Http\Controllers\Web\RoleController;
use App\Modules\Team\Http\Controllers\Web\UserController;
use App\Modules\Team\Services\TeamFeatures;
use Illuminate\Support\Facades\Route;

Route::middleware('can:manage roles')->group(function () {
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->whereNumber('role')->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->whereNumber('role')->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->whereNumber('role')->name('roles.destroy');
});

if (app(TeamFeatures::class)->activityLog()) {
    Route::get('/activity', [ActivityLogController::class, 'index'])->middleware('can:view activity log')->name('activity.index');
}

Route::middleware('can:view users')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->whereNumber('user')->name('users.show');
});
Route::middleware('can:create users')->group(function () {
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
});
Route::middleware('can:edit users')->group(function () {
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
});
Route::delete('/users/{user}', [UserController::class, 'destroy'])
    ->middleware('can:delete users')
    ->name('users.destroy');
