<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:otp-send')->name('otp.send');
    Route::post('otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify')->name('otp.verify');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:token-refresh')->name('refresh');

    Route::middleware(['auth:api', 'jwt.access', 'not-blocked'])->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});
