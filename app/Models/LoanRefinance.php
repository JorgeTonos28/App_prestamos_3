<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanRefinance extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'payoff_amount' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function newLoan()
    {
        return $this->belongsTo(Loan::class, 'new_loan_id');
    }

    public function oldLoan()
    {
        return $this->belongsTo(Loan::class, 'old_loan_id');
    }
}
