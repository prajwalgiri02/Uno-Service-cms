<?php

use App\Http\Controllers\cms\VendorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('vendor-approvals')->name('vendor-approvals.')->controller(VendorController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('{shop}', 'show')->name('show');
    Route::patch('{shop}/checklist', 'checklist')->name('checklist');
    Route::post('{shop}/decision', 'decide')->name('decide');
});
