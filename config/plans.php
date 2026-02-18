<?php

return [
    'currency' => 'DOP',
    'base_monthly_price' => (int) env('SUBSCRIPTION_BASE_MONTHLY_PRICE', 2500),
    'billing_cycles' => [
        'monthly' => [
            'label' => 'Mensual',
            'months' => 1,
            'discount_percent' => 0,
            'stripe_price_id' => env('STRIPE_PRICE_MONTHLY'),
        ],
        'quarterly' => [
            'label' => 'Trimestral',
            'months' => 3,
            'discount_percent' => 5,
            'stripe_price_id' => env('STRIPE_PRICE_QUARTERLY'),
        ],
        'semiannual' => [
            'label' => 'Semestral',
            'months' => 6,
            'discount_percent' => 10,
            'stripe_price_id' => env('STRIPE_PRICE_SEMIANNUAL'),
        ],
        'annual' => [
            'label' => 'Anual',
            'months' => 12,
            'discount_percent' => 20,
            'stripe_price_id' => env('STRIPE_PRICE_ANNUAL'),
        ],
    ],
];
