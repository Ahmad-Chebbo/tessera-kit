<?php

use App\Modules\Attendance\Http\Controllers\Api\V1\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('module:attendance')->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/current', [AttendanceController::class, 'current'])->name('attendance.current');
});

Route::middleware('can:record attendance')->group(function () {
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
});

Route::get('/attendance/report', [AttendanceController::class, 'report'])->middleware('can:view attendance')->name('attendance.report');
