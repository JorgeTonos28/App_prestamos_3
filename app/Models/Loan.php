<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function (Loan $loan) {
            if (!empty($loan->code)) {
                return;
            }

            $year = now()->year;
            $latestLoan = self::whereYear('created_at', $year)
                ->where('code', 'like', "LN-{$year}-%")
                ->orderByDesc('code')
                ->first();

            $nextSequence = 1;
            if ($latestLoan?->code) {
                $parts = explode('-', $latestLoan->code);
                $lastSequence = (int) end($parts);
                $nextSequence = $lastSequence + 1;
            }

            $loan->code = sprintf(
                'LN-%d-%s',
                $year,
                str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT)
            );
        });
    }

    protected $casts = [
        'start_date' => 'date',
        'maturity_date' => 'date',
        'last_accrual_date' => 'date',
        'next_due_date' => 'date',
        'monthly_rate' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'principal_initial' => 'decimal:2',
        'principal_outstanding' => 'decimal:2',
        'interest_accrued' => 'decimal:2',
        'fees_accrued' => 'decimal:2',
        'balance_total' => 'decimal:2',
        'cancellation_date' => 'date',
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

    public function consolidatedInto()
    {
        return $this->belongsTo(Loan::class, 'consolidated_into_loan_id');
    }

    public function consolidatedSourceLoans()
    {
        return $this->hasMany(Loan::class, 'consolidated_into_loan_id');
    }
}
