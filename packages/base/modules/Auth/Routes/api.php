<?php

use App\Modules\Auth\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::get('/me', [TokenController::class, 'me'])->name('me');
Route::delete('/auth/token', [TokenController::class, 'destroy'])->name('auth.token.destroy');
