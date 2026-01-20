<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Loan;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('clients', ClientController::class);
    Route::post('/loans/calculate-amortization', [LoanController::class, 'calculateAmortization'])->name('loans.calculate-amortization');
    Route::resource('loans', LoanController::class);
    Route::post('/loans/{loan}/payments', [PaymentController::class, 'store'])->name('loans.payments.store');
    Route::delete('/loans/{loan}/payments/{payment}', [PaymentController::class, 'destroy'])->name('loans.payments.destroy');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
});

require __DIR__.'/auth.php';
