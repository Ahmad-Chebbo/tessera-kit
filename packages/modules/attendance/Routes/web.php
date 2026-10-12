<?php

use App\Modules\Attendance\Http\Controllers\Web\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('module:attendance')->name('attendance.index');
Route::middleware('can:record attendance')->group(function () {
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
});
Route::middleware('can:view attendance')->group(function () {
    Route::get('/attendance/absences', [AttendanceController::class, 'absences'])->name('attendance.absences');
    Route::get('/attendance/report', [AttendanceController::class, 'report'])->name('attendance.report');
});
Route::middleware('can:manage attendance')->group(function () {
    Route::post('/attendance/records', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::put('/attendance/records/{record}', [AttendanceController::class, 'update'])->name('attendance.update');
    Route::delete('/attendance/records/{record}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');
    Route::post('/attendance/excuses', [AttendanceController::class, 'storeExcuse'])->name('attendance.excuses.store');
    Route::delete('/attendance/excuses/{excuse}', [AttendanceController::class, 'destroyExcuse'])->name('attendance.excuses.destroy');
});
