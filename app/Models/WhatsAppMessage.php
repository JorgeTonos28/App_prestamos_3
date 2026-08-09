<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'metadata' => 'encrypted:array',
            'error_message' => 'encrypted',
            'provider_timestamp' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function application()
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function document()
    {
        return $this->hasOne(ApplicantDocument::class);
    }
}
