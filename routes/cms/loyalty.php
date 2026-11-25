<?php

use App\Http\Controllers\cms\LoyaltyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('loyalty-points')->name('loyalty-points.')->controller(LoyaltyController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::put('rules', 'updateRules')->name('rules');
    Route::get('{user}', 'show')->name('show');
    Route::post('{user}/adjust', 'adjust')->name('adjust');
    Route::post('{user}/block', 'toggleBlock')->name('block');
});
