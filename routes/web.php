<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ApplicantDocumentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LabsMobileWebhookController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanApplicationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\WhatsAppIntegrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/webhooks/labsmobile/delivery', [LabsMobileWebhookController::class, 'delivery'])
    ->name('webhooks.labsmobile.delivery');

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->middleware('throttle:60,1')
    ->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->middleware('throttle:300,1')
    ->name('webhooks.whatsapp.receive');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('clients', ClientController::class);
    Route::patch('/clients/{client}/status', [ClientController::class, 'updateStatus'])->name('clients.status');
    Route::post('/loans/calculate-amortization', [LoanController::class, 'calculateAmortization'])->name('loans.calculate-amortization');
    Route::post('/loans/calculate-installment', [LoanController::class, 'calculateInstallment'])->name('loans.calculate-installment');
    Route::get('/loans/legal', [LoanController::class, 'legalIndex'])->name('loans.legal');
    Route::resource('loans', LoanController::class);
    Route::post('/loans/{loan}/payments', [PaymentController::class, 'store'])->name('loans.payments.store');
    Route::delete('/loans/{loan}/payments/{payment}', [PaymentController::class, 'destroy'])->name('loans.payments.destroy');
    Route::post('/loans/{loan}/cancel', [LoanController::class, 'cancel'])->name('loans.cancel');
    Route::post('/loans/{loan}/adjustments/open', [LoanController::class, 'openAdjustment'])->name('loans.adjustments.open');
    Route::post('/loans/{loan}/adjustments/close', [LoanController::class, 'closeAdjustment'])->name('loans.adjustments.close');
    Route::post('/loans/archive', [LoanController::class, 'archive'])->name('loans.archive');
    Route::post('/loans/{loan}/legal-fees', [LoanController::class, 'addLegalFee'])->name('loans.legal-fees.store');
    Route::get('/loans/{loan}/legal-contract', [LoanController::class, 'downloadLegalContract'])->name('loans.legal-contract');
    Route::get('/loans/{loan}/legal-summary', [LoanController::class, 'legalSummary'])->name('loans.legal-summary');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::get('/settings/sms', fn () => redirect()->route('settings.edit', ['tab' => 'sms']))->name('settings.sms');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/sms/send', [SmsController::class, 'store'])->name('sms.send');
    Route::post('/settings/sms/balance', [SmsController::class, 'refreshBalance'])->name('settings.sms.balance');

    Route::get('/loan-applications', [LoanApplicationController::class, 'index'])->name('loan-applications.index');
    Route::get('/loan-applications/{loanApplication}', [LoanApplicationController::class, 'show'])->name('loan-applications.show');
    Route::post('/loan-applications/{loanApplication}/decision', [LoanApplicationController::class, 'decide'])->name('loan-applications.decision');
    Route::post('/loan-applications/{loanApplication}/reanalyze', [LoanApplicationController::class, 'reanalyze'])->name('loan-applications.reanalyze');
    Route::get('/applicant-documents/{applicantDocument}/download', [ApplicantDocumentController::class, 'download'])->name('applicant-documents.download');
    Route::post('/applicant-documents/{applicantDocument}/review', [ApplicantDocumentController::class, 'review'])->name('applicant-documents.review');
    Route::post('/settings/whatsapp/test', [WhatsAppIntegrationController::class, 'test'])->name('settings.whatsapp.test');
});

require __DIR__.'/auth.php';
