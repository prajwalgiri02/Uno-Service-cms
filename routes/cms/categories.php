<?php

use App\Http\Controllers\cms\CategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('category-manager')->name('category-manager.')->controller(CategoryController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('{category}', 'update')->name('update');
    Route::delete('{category}', 'destroy')->name('destroy');
});
