<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Cashier\Http\Controllers\WebhookController;

Route::get('/', function () {
    return Inertia::render('Landing');
})->name('landing');

Route::post('/stripe/webhook', [WebhookController::class, 'handleWebhook'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('cashier.webhook');

Route::middleware(['auth', 'verified', 'enforce.subscription'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('clients', ClientController::class);
    Route::post('/loans/calculate-amortization', [LoanController::class, 'calculateAmortization'])->name('loans.calculate-amortization');
    Route::post('/loans/calculate-installment', [LoanController::class, 'calculateInstallment'])->name('loans.calculate-installment');
    Route::get('/loans/legal', [LoanController::class, 'legalIndex'])->name('loans.legal');
    Route::resource('loans', LoanController::class);
    Route::post('/loans/{loan}/payments', [PaymentController::class, 'store'])->name('loans.payments.store');
    Route::delete('/loans/{loan}/payments/{payment}', [PaymentController::class, 'destroy'])->name('loans.payments.destroy');
    Route::post('/loans/{loan}/cancel', [LoanController::class, 'cancel'])->name('loans.cancel');
    Route::post('/loans/{loan}/legal-fees', [LoanController::class, 'addLegalFee'])->name('loans.legal-fees.store');
    Route::get('/loans/{loan}/legal-contract', [LoanController::class, 'downloadLegalContract'])->name('loans.legal-contract');
    Route::get('/loans/{loan}/legal-summary', [LoanController::class, 'legalSummary'])->name('loans.legal-summary');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('settings.subscription');
    Route::post('/subscription/setup-intent', [SubscriptionController::class, 'setupIntent'])->name('settings.subscription.setup-intent');
    Route::post('/subscription/payment-method', [SubscriptionController::class, 'updatePaymentMethod'])->name('settings.subscription.payment-method');
    Route::post('/subscription/subscribe', [SubscriptionController::class, 'subscribe'])->name('settings.subscription.subscribe');
    Route::get('/subscription/invoices/{invoiceId}', [SubscriptionController::class, 'invoiceDownload'])->name('settings.subscription.invoices.download');
});

require __DIR__.'/auth.php';
