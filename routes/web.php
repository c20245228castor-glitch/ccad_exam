<?php

use App\Http\Controllers\ConsumerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WaterBillController;
use App\Http\Controllers\WaterMeterController;
use App\Http\Controllers\WaterRateController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome'))->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // These routes match the methods currently implemented in the controllers.
    Route::resource('consumers', ConsumerController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::resource('water-meters', WaterMeterController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::resource('water-rates', WaterRateController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::resource('water-bills', WaterBillController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);

    // Payments are immutable in this version: record and view, but do not edit/delete.
    Route::resource('payments', PaymentController::class)
        ->only(['index', 'store', 'show']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
