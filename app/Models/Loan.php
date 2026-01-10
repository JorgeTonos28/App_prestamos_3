<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'last_accrual_date' => 'date',
        'next_due_date' => 'date',
        'monthly_rate' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'principal_initial' => 'decimal:2',
        'principal_outstanding' => 'decimal:2',
        'interest_accrued' => 'decimal:2',
        'fees_accrued' => 'decimal:2',
        'balance_total' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(LoanLedgerEntry::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
