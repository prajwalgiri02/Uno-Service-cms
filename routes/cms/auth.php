<?php

use App\Http\Controllers\cms\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->controller(LoginController::class)->group(function () {
    Route::get('/login', 'create')->name('auth.login');
    Route::post('/login', 'store')->name('auth.login.store')->middleware('throttle:10,1');
});

Route::middleware('auth')->post('/logout', [LoginController::class, 'destroy'])->name('auth.logout');
