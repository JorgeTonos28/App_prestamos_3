<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition()
    {
        return [
            'client_id' => Client::factory(),
            'code' => $this->faker->unique()->bothify('LN-####'),
            'status' => 'draft',
            'start_date' => $this->faker->date(),
            'modality' => 'monthly',
            'monthly_rate' => 5.00,
            'interest_mode' => 'simple',
            'interest_base' => 'principal',
            'days_in_month_convention' => 30,
            'installment_amount' => 1000.00,
            'late_fee_cutoff_mode' => 'dynamic_payment',
            'payment_accrual_mode' => 'realtime',
            'cutoff_anchor_date' => now()->toDateString(),
            'cutoff_cycle_mode' => 'calendar',
            'month_day_count_mode' => 'exact',
            'late_fee_trigger_type' => 'days',
            'late_fee_trigger_value' => null,
            'late_fee_day_type' => 'business',
            'principal_initial' => 10000.00,
            'principal_outstanding' => 10000.00,
            'interest_accrued' => 0.00,
            'fees_accrued' => 0.00,
            'balance_total' => 10000.00,
            'currency' => 'DOP',
        ];
    }
}
