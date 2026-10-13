<?php

use App\Modules\Leave\Http\Controllers\Web\LeaveController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:request leave')->group(function () {
    Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::post('/leave/requests', [LeaveController::class, 'store'])->name('leave.requests.store');
    Route::post('/leave/requests/{leaveRequest}/cancel', [LeaveController::class, 'cancel'])->name('leave.requests.cancel');
});
Route::middleware('can:approve leave')->group(function () {
    Route::post('/leave/requests/{leaveRequest}/approve', [LeaveController::class, 'approve'])->name('leave.requests.approve');
    Route::post('/leave/requests/{leaveRequest}/reject', [LeaveController::class, 'reject'])->name('leave.requests.reject');
});
Route::middleware('can:manage leave')->group(function () {
    Route::post('/leave/types', [LeaveController::class, 'storeType'])->name('leave.types.store');
    Route::put('/leave/types/{type}', [LeaveController::class, 'updateType'])->name('leave.types.update');
    Route::delete('/leave/types/{type}', [LeaveController::class, 'destroyType'])->name('leave.types.destroy');
    Route::post('/leave/holidays', [LeaveController::class, 'storeHoliday'])->name('leave.holidays.store');
    Route::delete('/leave/holidays/{holiday}', [LeaveController::class, 'destroyHoliday'])->name('leave.holidays.destroy');
});
