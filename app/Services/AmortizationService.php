<?php

namespace App\Services;

use Carbon\Carbon;

class AmortizationService
{
    /**
     * Generate an amortization schedule based on fixed installment amount.
     */
    public function generateSchedule(
        float $principal,
        float $monthlyRate, // Percentage 0-100
        string $modality,
        float $installmentAmount,
        string $startDate,
        string $interestMode = 'simple', // simple (on principal) or compound (on balance)
        int $daysInMonthConvention = 30
    ): array {
        $schedule = [];
        $balance = $principal;
        $date = Carbon::parse($startDate);
        $period = 1;

        // Safety break to prevent infinite loops
        $maxPeriods = 600;

        // Determine period length in days
        $daysInPeriod = match ($modality) {
            'daily' => 1,
            'weekly' => 7,
            'biweekly' => 15,
            'monthly' => $daysInMonthConvention,
            default => 30
        };

        $dailyRate = ($monthlyRate / 100) / $daysInMonthConvention;
        $periodRate = $dailyRate * $daysInPeriod;

        // Check if installment covers interest
        $initialInterest = $principal * $periodRate;
        if ($installmentAmount <= $initialInterest) {
            return [
                'error' => 'La cuota es insuficiente para cubrir los intereses. El préstamo nunca terminará.',
                'min_installment' => ceil($initialInterest * 1.01) // Suggest slightly more
            ];
        }

        while ($balance > 0.05 && $period <= $maxPeriods) {
            // Calculate Interest
            // Logic matches InterestEngine:
            // If Simple: Base = Principal Outstanding
            // If Compound: Base = Total Balance (Here we assume Balance includes accrued interest if we were tracking it separate,
            // but for a projection, we usually assume the payment covers interest immediately so Balance IS Principal in Simple mode).

            $interest = $balance * $periodRate;

            // Payment logic
            $amount = $installmentAmount;

            // If last payment is less than installment
            // Calculate what is needed to close: Balance + Interest
            if (($balance + $interest) < $amount) {
                $amount = $balance + $interest;
            }

            $principalPay = $amount - $interest;
            $balance -= $principalPay;

            // Advance Date
            $this->advanceDate($date, $modality);

            $schedule[] = [
                'period' => $period,
                'date' => $date->toDateString(),
                'installment' => round($amount, 2),
                'interest' => round($interest, 2),
                'principal' => round($principalPay, 2),
                'balance' => max(0, round($balance, 2))
            ];

            $period++;
        }

        return $schedule;
    }

    private function advanceDate(Carbon $date, string $modality): void
    {
        match ($modality) {
            'daily' => $date->addDay(),
            'weekly' => $date->addWeek(),
            'biweekly' => $date->addWeeks(2),
            'monthly' => $date->addMonth(),
            default => $date->addMonth()
        };
    }
}
