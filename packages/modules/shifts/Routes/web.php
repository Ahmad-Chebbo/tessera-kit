<?php

use App\Modules\Shifts\Http\Controllers\Web\ShiftController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:view shifts')->group(function () {
    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
});
Route::middleware('can:manage shifts')->group(function () {
    Route::post('/shifts/templates', [ShiftController::class, 'storeTemplate'])->name('shifts.templates.store');
    Route::put('/shifts/templates/{template}', [ShiftController::class, 'updateTemplate'])->name('shifts.templates.update');
    Route::delete('/shifts/templates/{template}', [ShiftController::class, 'destroyTemplate'])->name('shifts.templates.destroy');
    Route::post('/shifts/assignments', [ShiftController::class, 'storeAssignment'])->name('shifts.assignments.store');
    Route::delete('/shifts/assignments/{assignment}', [ShiftController::class, 'destroyAssignment'])->name('shifts.assignments.destroy');
    Route::post('/shifts/cycles', [ShiftController::class, 'storeCycle'])->name('shifts.cycles.store');
    Route::post('/shifts/cycles/{cycle}/generate', [ShiftController::class, 'generateCycle'])->name('shifts.cycles.generate');
    Route::delete('/shifts/cycles/{cycle}', [ShiftController::class, 'destroyCycle'])->name('shifts.cycles.destroy');
});
