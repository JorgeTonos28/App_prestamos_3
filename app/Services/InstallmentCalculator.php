<?php

namespace App\Services;

class InstallmentCalculator
{
    /**
     * Calculate fixed installment amount based on loan parameters.
     * Strategy: Use PMT formula for fixed payment when target term is provided.
     */
    public function calculateInstallment(float $principal, float $monthlyRate, string $modality, int $daysInMonth = 30, ?int $targetTermPeriods = null): float
    {
        // 1. Determine days in period
        $daysInPeriod = match ($modality) {
            'daily' => 1,
            'weekly' => 7,
            'biweekly' => 15, // Configurable, but using 15 as standard
            'monthly' => $daysInMonth, // Usually 30 or real month days
        };

        // 2. Calculate rate per period
        // Rate per day = (monthlyRate / 100) / daysInMonth
        $dailyRate = $daysInMonth > 0 ? ($monthlyRate / 100) / $daysInMonth : 0;
        $ratePerPeriod = $dailyRate * $daysInPeriod;

        if (!$targetTermPeriods || $targetTermPeriods <= 0) {
            $interestPerPeriod = $principal * $ratePerPeriod;
            return round($interestPerPeriod, 2);
        }

        // 3. PMT formula for fixed installment
        if ($ratePerPeriod == 0.0) {
            $installment = $principal / $targetTermPeriods;
        } else {
            $factor = pow(1 + $ratePerPeriod, $targetTermPeriods);
            $installment = $principal * ($ratePerPeriod * $factor) / ($factor - 1);
        }

        // 4. Total
        return round($installment, 2);
    }
}
