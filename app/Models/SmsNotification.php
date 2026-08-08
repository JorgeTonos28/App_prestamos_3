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
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
