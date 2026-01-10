<?php

namespace App\Services;

class InstallmentCalculator
{
    /**
     * Calculate fixed installment amount based on loan parameters.
     * Strategy: Interest Only + Amortization (if target_term_periods set).
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

        // 2. Calculate Expected Interest per period
        // Rate per day = (monthlyRate / 100) / daysInMonth
        $dailyRate = ($monthlyRate / 100) / $daysInMonth;
        $interestPerPeriod = $principal * $dailyRate * $daysInPeriod;

        // 3. Calculate Principal Amortization Part
        $principalPart = 0;
        if ($targetTermPeriods && $targetTermPeriods > 0) {
            $principalPart = $principal / $targetTermPeriods;
        }

        // 4. Total
        return round($interestPerPeriod + $principalPart, 2); // Rounding to 2 decimals or ceiling? Using round for now.
    }
}
