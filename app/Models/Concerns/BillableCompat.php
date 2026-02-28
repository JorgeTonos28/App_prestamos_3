<?php

namespace App\Models\Concerns;

if (trait_exists(\Laravel\Cashier\Billable::class)) {
    trait BillableCompat
    {
        use \Laravel\Cashier\Billable;
    }
} else {
    trait BillableCompat
    {
        // Fallback no-op trait for environments where Cashier is not installed.
    }
}
