<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanLedgerEntryFactory extends Factory
{
    protected $model = LoanLedgerEntry::class;

    public function definition()
    {
        return [
            'loan_id' => Loan::factory(),
            'type' => 'disbursement',
            'occurred_at' => now(),
            'amount' => 1000.00,
            'principal_delta' => 1000.00,
            'interest_delta' => 0.00,
            'fees_delta' => 0.00,
            'balance_after' => 1000.00,
            'meta' => null,
        ];
    }
}
