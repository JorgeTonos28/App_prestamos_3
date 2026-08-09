<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanApplicationEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'encrypted:array',
            'created_at' => 'datetime',
        ];
    }

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
