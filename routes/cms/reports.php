<?php

use App\Http\Controllers\cms\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('report-queue')->name('report-queue.')->controller(ReportController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('{report}/resolve', 'resolve')->name('resolve');
});
