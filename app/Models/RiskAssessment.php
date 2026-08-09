<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskAssessment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'summary' => 'encrypted',
            'report' => 'encrypted',
            'factors' => 'encrypted:array',
            'red_flags' => 'encrypted:array',
            'mitigants' => 'encrypted:array',
            'deterministic_breakdown' => 'encrypted:array',
            'input_snapshot' => 'encrypted:array',
            'error_message' => 'encrypted',
            'generated_at' => 'datetime',
        ];
    }

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }
}
