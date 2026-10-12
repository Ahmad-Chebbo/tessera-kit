<?php

use App\Modules\Auth\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [TokenController::class, 'store'])->name('auth.token.store');
