<?php

use App\Http\Controllers\Api\BlockedPlaceController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckInController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\ErrandController;
use App\Http\Controllers\Api\FreeWindowController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\NudgeController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\SupportTicketController;
use Illuminate\Support\Facades\Route;

Route::get('config', ConfigController::class)->name('config');
Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');

Route::middleware(['auth:api', 'jwt.access', 'not-blocked'])->group(function () {
    Route::prefix('me')->name('me.')->controller(ProfileController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::patch('/', 'update')->name('update');
        Route::post('pause-today', 'pauseToday')->name('pause');
        Route::delete('pause-today', 'resume')->name('resume');
        Route::get('alert-health', 'alertHealth')->name('alert-health');
        Route::post('devices', 'registerDevice')->name('devices.store');
        Route::delete('devices/{deviceId}', 'removeDevice')->name('devices.destroy');
        Route::get('stats', 'stats')->name('stats');
        Route::get('visits', 'visits')->name('visits');
    });

    Route::apiResource('me/free-windows', FreeWindowController::class)->except('show')->parameters(['free-windows' => 'freeWindow'])->names('me.free-windows');
    Route::apiResource('me/blocked-places', BlockedPlaceController::class)->except('show')->parameters(['blocked-places' => 'blockedPlace'])->names('me.blocked-places');

    Route::get('home', HomeController::class)->name('home');

    Route::get('categories/{category}/price-hint', [CategoryController::class, 'priceHint'])->name('categories.price-hint');
    Route::post('category-requests', [CategoryController::class, 'requestNew'])->middleware('throttle:20,60')->name('category-requests.store');

    Route::controller(ErrandController::class)->prefix('errands')->name('errands.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('{errand}', 'update')->name('update');
        Route::delete('{errand}', 'destroy')->name('destroy');
        Route::post('{errand}/keep', 'keep')->name('keep');
        Route::post('{errand}/done', 'done')->name('done');
        Route::get('{errand}/matches', 'matches')->name('matches');
        Route::post('pause-category/{category}', 'pauseCategory')->name('pause-category');
    });

    Route::controller(ShopController::class)->prefix('shops')->name('shops.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('favorites', 'favorites')->name('favorites');
        Route::get('{shop}', 'show')->name('show');
        Route::post('{shop}/favorite', 'favorite')->name('favorite');
        Route::delete('{shop}/favorite', 'unfavorite')->name('unfavorite');
        Route::post('{shop}/report', 'report')->middleware('throttle:10,60')->name('report');
    });

    Route::controller(NudgeController::class)->prefix('nudges')->name('nudges.')->group(function () {
        Route::post('check', 'check')->middleware('throttle:nudge-check')->name('check');
        Route::get('today', 'today')->name('today');
        Route::get('/', 'index')->name('index');
        Route::get('{nudge}', 'show')->name('show');
        Route::post('{nudge}/opened', 'opened')->name('opened');
    });

    Route::controller(CheckInController::class)->prefix('check-ins')->name('check-ins.')->group(function () {
        Route::post('/', 'store')->middleware('throttle:check-in')->name('store');
        Route::get('visit-code', 'visitCode')->name('visit-code');
        Route::post('{checkIn}/review', 'review')->name('review');
    });

    Route::controller(SupportTicketController::class)->prefix('support-tickets')->name('support-tickets.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:10,60')->name('store');
        Route::get('{supportTicket}', 'show')->name('show');
        Route::post('{supportTicket}/messages', 'reply')->name('reply');
    });
});
