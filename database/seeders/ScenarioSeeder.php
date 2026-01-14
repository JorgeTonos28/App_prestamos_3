<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Loan;
use App\Services\InterestEngine;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $interestEngine = new InterestEngine();
        $paymentService = new PaymentService($interestEngine);

        // Scenario 1: Active Loan, paying on time
        $client1 = Client::firstOrCreate(
            ['national_id' => '001-0000001-1'],
            [
                'first_name' => 'Juan',
                'last_name' => 'Pérez',
                'phone' => '809-555-0101',
                'status' => 'active'
            ]
        );

        if (!Loan::where('code', 'LN-1001')->exists()) {
            $startDate = Carbon::now()->subMonths(1);
            $periods = 12;
            $maturityDate = $startDate->copy()->addMonths($periods);

            $loan1 = Loan::create([
                'client_id' => $client1->id,
                'code' => 'LN-1001',
                'status' => 'active',
                'start_date' => $startDate,
                'modality' => 'monthly',
                'monthly_rate' => 5, // 5%
                'interest_mode' => 'simple',
                'days_in_month_convention' => 30,
                'installment_amount' => 1500, // Dummy
                'principal_initial' => 10000,
                'principal_outstanding' => 10000,
                'balance_total' => 10000,
                'last_accrual_date' => $startDate,
                'target_term_periods' => $periods,
                'maturity_date' => $maturityDate
            ]);

            // Disburse
            $loan1->ledgerEntries()->create([
                'type' => 'disbursement',
                'occurred_at' => $loan1->start_date,
                'amount' => 10000,
                'principal_delta' => 10000,
                'interest_delta' => 0,
                'fees_delta' => 0,
                'balance_after' => 10000,
            ]);

            // Accrue for 1 month
            $interestEngine->accrueUpTo($loan1, Carbon::now());
        }


        // Scenario 2: Overdue Loan (3 months old, no payment)
        $client2 = Client::firstOrCreate(
            ['national_id' => '001-0000002-2'],
            [
                'first_name' => 'Maria',
                'last_name' => 'Gomez',
                'phone' => '809-555-0202',
                'status' => 'active'
            ]
        );

        if (!Loan::where('code', 'LN-1002')->exists()) {
            $startDate = Carbon::now()->subMonths(3);
            $periods = 6;
            $maturityDate = $startDate->copy()->addMonths($periods);

            $loan2 = Loan::create([
                'client_id' => $client2->id,
                'code' => 'LN-1002',
                'status' => 'active',
                'start_date' => $startDate,
                'modality' => 'monthly',
                'monthly_rate' => 10, // High risk
                'principal_initial' => 50000,
                'principal_outstanding' => 50000,
                'balance_total' => 50000,
                'installment_amount' => 5000,
                'last_accrual_date' => $startDate,
                'target_term_periods' => $periods,
                'maturity_date' => $maturityDate
            ]);

            $loan2->ledgerEntries()->create([
                'type' => 'disbursement',
                'occurred_at' => $loan2->start_date,
                'amount' => 50000,
                'principal_delta' => 50000,
                'interest_delta' => 0,
                'fees_delta' => 0,
                'balance_after' => 50000,
            ]);

            $interestEngine->accrueUpTo($loan2, Carbon::now());
        }

        // Scenario 3: Closed Loan (Fully Paid)
        $client3 = Client::firstOrCreate(
            ['national_id' => '001-0000003-3'],
            [
                'first_name' => 'Pedro',
                'last_name' => 'Martinez',
                'status' => 'active'
            ]
        );

        if (!Loan::where('code', 'LN-1003')->exists()) {
            $startDate = Carbon::now()->subMonths(2);
            $periods = 3;
            $maturityDate = $startDate->copy()->addMonths($periods);

            $loan3 = Loan::create([
                'client_id' => $client3->id,
                'code' => 'LN-1003',
                'status' => 'active', // Will be closed by payment
                'start_date' => $startDate,
                'monthly_rate' => 3,
                'principal_initial' => 5000,
                'principal_outstanding' => 5000,
                'balance_total' => 5000,
                'installment_amount' => 1000,
                'last_accrual_date' => $startDate,
                'target_term_periods' => $periods,
                'maturity_date' => $maturityDate,
                'modality' => 'monthly' // Fixed missing modality
            ]);

            $loan3->ledgerEntries()->create([
                'type' => 'disbursement',
                'occurred_at' => $loan3->start_date,
                'amount' => 5000,
                'principal_delta' => 5000,
                'interest_delta' => 0,
                'fees_delta' => 0,
                'balance_after' => 5000,
            ]);

            // Accrue 1 month
            $payDate = Carbon::now()->subMonths(1);
            $interestEngine->accrueUpTo($loan3, $payDate);

            // Pay Full Balance
            $paymentService->registerPayment(
                $loan3,
                $payDate,
                $loan3->balance_total,
                'cash',
                'FULL-PAYOFF'
            );
        }
    }
}
