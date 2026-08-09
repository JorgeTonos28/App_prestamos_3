<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppConversation extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_conversations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'profile_name' => 'encrypted',
            'context' => 'encrypted:array',
            'last_message_at' => 'datetime',
            'customer_service_window_expires_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_conversation_id');
    }

    public function isInsideCustomerServiceWindow(): bool
    {
        return $this->customer_service_window_expires_at?->isFuture() ?? false;
    }
}
