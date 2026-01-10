<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanLedgerEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
        'amount' => 'decimal:2',
        'principal_delta' => 'decimal:2',
        'interest_delta' => 'decimal:2',
        'fees_delta' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta' => 'array',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
