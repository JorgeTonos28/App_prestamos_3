<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppWebhookEvent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'failure_reason' => 'encrypted',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
