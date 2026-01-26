<?php

namespace App\Services;

use App\Models\Loan;
use Carbon\Carbon;

class ArrearsCalculator
{
    /**
     * Calculate arrears status for a loan.
     */
    public function calculate(Loan $loan): array
    {
        $startDate = $loan->start_date;
        $installmentAmount = $loan->installment_amount;

        // If loan has no strict installment amount, we can't calculate arrears by installments
        if (!$installmentAmount || $installmentAmount <= 0) {
            return [
                'count' => 0,
                'amount' => 0,
                'days' => 0,
                'details' => []
            ];
        }

        $modality = $loan->modality;
        $now = Carbon::now()->startOfDay();

        // 1. Generate Due Dates from start until now (strict past dates only)
        // If a payment is due TODAY, it is not yet in arrears until tomorrow.
        $dueDates = [];
        $currentDate = $startDate->copy();

        // Move to first due date
        $this->advanceDate($currentDate, $modality);

        // Comparison uses startOfDay, so if currentDate is today (00:00) and now is today (00:00), lt is false.
        while ($currentDate->lt($now)) {
            $dueDates[] = $currentDate->copy();
            $this->advanceDate($currentDate, $modality);
        }

        if (empty($dueDates)) {
             return [
                'count' => 0,
                'amount' => 0,
                'days' => 0,
                'details' => []
            ];
        }

        // 2. Calculate Total Expected vs Total Paid
        $totalExpected = count($dueDates) * $installmentAmount;

        // Use total_paid from ledger (payments only)
        // Optimization: Loan model could cache this, but for now we query relationships or use ledger
        // Assuming loan->payments relation is loaded or we query it.
        // Or better, use ledger entries of type payment.
        // For efficiency, let's assume Loan model has `principal_initial` and `balance_total`.
        // Total Paid = (Principal Initial + Interest Accrued + Fees) - Balance Total ??
        // No, that's complex because interest accrues daily.

        // Simpler: Sum of all payment transactions.
        if ($loan->relationLoaded('ledgerEntries')) {
            $totalPaid = $loan->ledgerEntries
                ->where('type', 'payment')
                ->sum('amount');
        } else {
            $totalPaid = $loan->ledgerEntries()
                ->where('type', 'payment')
                ->sum('amount');
        }

        // 3. Arrears
        $arrearsAmount = max(0, $totalExpected - $totalPaid);

        // Count = Amount / Installment. Round down because partial payment doesn't clear a full installment usually,
        // but for "count" usually we want number of FULLY missed or partially missed?
        // User asked: "Si tiene 3 meses sin pagar... debe 3 meses".
        // If arrears is 3.5 installments, it's 3.5 months overdue.
        // Let's return float for precision, but show Integer in UI if clean.
        $arrearsCount = $arrearsAmount / $installmentAmount;

        // Days overdue? From the first unpaid due date.
        // If totalPaid covers first X due dates, find the X+1 date.
        $coveredInstallments = floor($totalPaid / $installmentAmount);
        $firstUnpaidIndex = (int) $coveredInstallments;

        $daysOverdue = 0;
        if (isset($dueDates[$firstUnpaidIndex])) {
            $firstUnpaidDate = $dueDates[$firstUnpaidIndex];
            $daysOverdue = $firstUnpaidDate->diffInDays($now);
        }

        return [
            'count' => round($arrearsCount, 1),
            'amount' => $arrearsAmount,
            'days' => $daysOverdue,
            'expected_to_date' => $totalExpected,
            'paid_to_date' => $totalPaid
        ];
    }

    private function advanceDate(Carbon $date, string $modality): void
    {
        match ($modality) {
            'daily' => $date->addDay(),
            'weekly' => $date->addWeek(),
            'biweekly' => $date->addWeeks(2),
            'monthly' => $date->addMonth(),
        };
    }
}
