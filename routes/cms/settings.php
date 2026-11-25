<?php

use App\Http\Controllers\cms\NudgeRuleController;
use App\Http\Controllers\cms\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('nudge-rules', [NudgeRuleController::class, 'edit'])->name('nudge-rules.index');
    Route::put('nudge-rules', [NudgeRuleController::class, 'update'])->name('nudge-rules.update');

    Route::controller(SettingsController::class)->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', 'edit')->name('index');
        Route::put('profile', 'updateProfile')->name('profile');
        Route::put('password', 'updatePassword')->name('password');
        Route::post('admins', 'storeAdmin')->name('admins.store');
    });
});
