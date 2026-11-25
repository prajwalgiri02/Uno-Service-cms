<?php

use App\Http\Controllers\cms\SupportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('support-desk')->name('support-desk.')->controller(SupportController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('{ticket}', 'show')->name('show');
    Route::post('{ticket}/reply', 'reply')->name('reply');
    Route::patch('{ticket}/status', 'status')->name('status');
});
