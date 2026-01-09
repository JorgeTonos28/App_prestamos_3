<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PaymentController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Loan;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard', [
        'active_loans_count' => Loan::where('status', 'active')->count(),
        'portfolio_balance' => Loan::where('status', 'active')->sum('balance_total'),
        'overdue_count' => Loan::where('status', 'active')->whereDate('next_due_date', '<', now())->count(), // Simple logic for now
        //'recent_payments' => \App\Models\Payment::with('client')->latest()->take(5)->get()
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('clients', ClientController::class);
    Route::resource('loans', LoanController::class);
    Route::post('/loans/{loan}/payments', [PaymentController::class, 'store'])->name('loans.payments.store');
});

require __DIR__.'/auth.php';
