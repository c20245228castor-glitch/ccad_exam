<?php

use App\Http\Controllers\ConsumerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WaterBillController;
use App\Http\Controllers\WaterMeterController;
use App\Http\Controllers\WaterRateController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('consumers', [ConsumerController::class, 'index'])->name('consumers.index');
    Route::get('water-meters', [WaterMeterController::class, 'index'])->name('water-meters.index');
    Route::get('water-bills', [WaterBillController::class, 'index'])->name('water-bills.index');
    Route::get('water-rates', [WaterRateController::class, 'index'])->name('water-rates.index');
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
