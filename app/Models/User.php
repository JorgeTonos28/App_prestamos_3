<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\BillableCompat;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use BillableCompat;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'social_id',
        'social_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function hasActiveSubscriptionAccess(): bool
    {
        if (! method_exists($this, 'subscription')) {
            return false;
        }

        $subscription = $this->subscription('default');

        return (bool) $subscription
            && $subscription->valid()
            && ! $subscription->pastDue()
            && ! $subscription->onGracePeriod();
    }

    public function subscriptionState(): string
    {
        if (! method_exists($this, 'subscription')) {
            return 'expired';
        }

        $subscription = $this->subscription('default');

        if (! $subscription) {
            return 'expired';
        }

        if ($subscription->pastDue() || $subscription->ended()) {
            return 'expired';
        }

        if ($subscription->onGracePeriod()) {
            return 'grace_period';
        }

        if ($subscription->valid()) {
            return 'active';
        }

        return 'expired';
    }
}
