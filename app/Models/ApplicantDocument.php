<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApplicantDocument extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (ApplicantDocument $document): void {
            $document->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'original_name' => 'encrypted',
            'validation_results' => 'encrypted:array',
            'rejection_reason' => 'encrypted',
            'received_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function message()
    {
        return $this->belongsTo(WhatsAppMessage::class, 'whatsapp_message_id');
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }
}
