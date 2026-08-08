<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsNotification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'notification_date' => 'date',
        'segment_count' => 'integer',
        'credits_used' => 'decimal:4',
        'estimated_cost' => 'decimal:4',
        'provider_response' => 'array',
        'delivery_details' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
