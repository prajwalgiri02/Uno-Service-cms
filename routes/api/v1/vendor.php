<?php

use App\Http\Controllers\Api\Vendor\CheckInController;
use App\Http\Controllers\Api\Vendor\InsightController;
use App\Http\Controllers\Api\Vendor\OfferController;
use App\Http\Controllers\Api\Vendor\ReviewController;
use App\Http\Controllers\Api\Vendor\ServiceController;
use App\Http\Controllers\Api\Vendor\ShopController;
use Illuminate\Support\Facades\Route;

Route::prefix('vendor')->name('vendor.')->middleware(['auth:api', 'jwt.access', 'not-blocked'])->group(function () {
    Route::post('shop', [ShopController::class, 'store'])->name('shop.store');

    Route::middleware('vendor.shop')->group(function () {
        Route::controller(ShopController::class)->group(function () {
            Route::get('shop', 'show')->name('shop.show');
            Route::patch('shop', 'update')->name('shop.update');
            Route::get('today', 'today')->name('today');
            Route::post('open-today', 'openToday')->name('open-today');
            Route::post('no-wait', 'noWait')->name('no-wait');
            Route::post('shop/photos', 'addPhoto')->name('photos.store');
            Route::delete('shop/photos/{photo}', 'deletePhoto')->name('photos.destroy');
            Route::get('check-in-code', 'checkInCode')->name('check-in-code');
            Route::post('check-in-code/rotate', 'rotateCheckInCode')->name('check-in-code.rotate');
        });

        Route::apiResource('services', ServiceController::class)->except('show');

        Route::get('offers', [OfferController::class, 'show'])->name('offers.show');
        Route::post('offers', [OfferController::class, 'store'])->name('offers.store');
        Route::delete('offers/current', [OfferController::class, 'destroy'])->name('offers.destroy');

        Route::post('check-ins', [CheckInController::class, 'store'])->middleware('throttle:check-in')->name('check-ins.store');

        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/{review}/reply', [ReviewController::class, 'reply'])->name('reviews.reply');

        Route::get('insights', InsightController::class)->name('insights');
    });
});
