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

    Route::middleware('role:admin')->group(function () {
        Route::resource('consumers', ConsumerController::class)
            ->parameters(['consumers' => 'id'])
            ->except(['create', 'edit']);
        Route::resource('water-meters', WaterMeterController::class)
            ->parameters(['water-meters' => 'id'])
            ->except(['create', 'edit']);
        Route::resource('water-bills', WaterBillController::class)
            ->parameters(['water-bills' => 'id'])
            ->except(['create', 'edit']);
        Route::resource('water-rates', WaterRateController::class)
            ->parameters(['water-rates' => 'id'])
            ->except(['create', 'edit']);
        Route::resource('payments', PaymentController::class)
            ->parameters(['payments' => 'id'])
            ->except(['create', 'edit']);
    });

    Route::post('my-bills/{billId}/pay', [PaymentController::class, 'payForConsumer'])
        ->middleware('role:user')
        ->name('consumer.bills.pay');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
