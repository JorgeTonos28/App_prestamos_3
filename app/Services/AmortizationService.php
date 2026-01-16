<?php

namespace App\Services;

use Carbon\Carbon;
use App\Helpers\FinancialHelper;

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
        int $daysInMonthConvention = 30,
        float $accruedInterest = 0.0 // Optional initial accrued interest (for ongoing loans)
    ): array {
        $schedule = [];
        // The "balance" tracks the total debt to be paid (Principal + Accrued Interest).
        $balance = $principal + $accruedInterest;

        // For simple interest, the "principal" base for interest calculation is just the principal part.
        // For compound, it's the total balance.
        $currentPrincipal = $principal;

        $date = Carbon::parse($startDate);
        $period = 1;

        // Safety break to prevent infinite loops
        $maxPeriods = 600;

        // Determine period length in days
        // Note: For Monthly modality with 30/360, we treat it as 30 days periodRate-wise
        // But if we used strict dates, we might have variance.
        // Given we use periodRate derived from monthlyRate, it's consistent.
        $daysInPeriod = match ($modality) {
            'daily' => 1,
            'weekly' => 7,
            'biweekly' => 15,
            'monthly' => $daysInMonthConvention,
            default => 30
        };

        $dailyRate = ($monthlyRate / 100) / $daysInMonthConvention;
        $periodRate = $dailyRate * $daysInPeriod;

        // Debug override: If Monthly and 30-day convention, Period Rate IS exactly Monthly Rate / 100.
        // logic: (Rate/30) * 30 = Rate. So it is correct.

        // Check if installment covers interest
        $initialInterest = $principal * $periodRate;
        if ($installmentAmount <= $initialInterest) {
            return [
                'error' => 'La cuota es insuficiente para cubrir los intereses. El préstamo nunca terminará.',
                'min_installment' => ceil($initialInterest * 1.01) // Suggest slightly more
            ];
        }

        while ($balance > 0.05 && $period <= $maxPeriods) {
            // Calculate Interest for this period
            $baseForInterest = ($interestMode === 'compound') ? $balance : $currentPrincipal;
            $periodInterest = $baseForInterest * $periodRate;

            // Payment logic
            $paymentAmount = $installmentAmount;

            // Adjust final payment if debt is small
            // Logic: If Balance + Period Interest < Payment, we just pay off everything.
            // Use a slightly larger epsilon to prevent tiny residuals
            if (($balance + $periodInterest) <= ($paymentAmount + 0.10)) {
                $paymentAmount = $balance + $periodInterest;
            }

            // Apply Payment
            // 1. Pay Interest (Period Interest)
            // Note: If we had initial accrued interest, technically we pay that first.
            // But $balance includes it. $periodInterest is just for THIS period.
            // So total interest to cover = Period Interest?
            // Wait. In simple interest, if I have 50 accrued, and 50 new. Total 100 interest.
            // My payment of 200 pays 100 interest, 100 principal.
            // My logic: $balance tracks TOTAL DEBT.
            // So if I add $periodInterest to debt? No, usually amortization table shows Interest part of payment.

            // Let's stick to standard amortization view:
            // Interest Part = $periodInterest.
            // Principal Part = Payment - Interest Part.

            // BUT, if we have initial accrued interest (from past), that is "interest" too.
            // However, the standard function assumes we are generating "future" schedule.
            // If we start with accrued interest, the first payment might need to cover THAT + period interest.
            // To simplify: We treat the "balance" as the bucket.
            // If simple interest: The "Interest" column usually shows the interest ACCRUED in that period.
            // But if we pay off old interest, it might look like "Principal" payment in a simple table if we don't track it.

            // Let's simplify for the user requirement: "Simple interest... interest base is principal_outstanding".
            // So $periodInterest is correct.

            $interestPart = $periodInterest;
            $principalPart = $paymentAmount - $interestPart;

            // Reduce Balance (Total Debt)
            // Payment reduces debt by $paymentAmount? No.
            // Total Debt = Previous Balance + New Interest - Payment.
            // Wait. Amortization tables usually don't "add" interest to balance unless capitalizing (compound).
            // In simple interest, if you pay, you pay interest first.
            // If Payment < Interest, balance grows? Or just arrears?
            // Assuming Payment >= Interest.

            // If we are strictly Simple Interest (Non-Capitalizing):
            // Balance (Principal) reduces by ($payment - $interest).
            // If we have "Accrued Interest" in $balance initially, we need to burn that down first.

            // Refined Logic for Simple Interest with Arrears/Accrual:
            // $balance starts as Principal + OldInterest.
            // $periodInterest = Principal * Rate.
            // Total Interest Owed = OldInterest + PeriodInterest.
            // We don't track "OldInterest" separately in this loop easily.
            // BUT $balance = Principal + OldInterest.
            // $currentPrincipal = Principal.
            // So OldInterest = $balance - $currentPrincipal.

            $accrued = ($interestMode === 'compound') ? 0 : ($balance - $currentPrincipal);
            $totalInterestToPay = $periodInterest + $accrued;

            // Payment is applied to Total Interest first.
            $appliedToInterest = min($paymentAmount, $totalInterestToPay);
            $appliedToPrincipal = $paymentAmount - $appliedToInterest;

            // Update State
            $balance -= $appliedToPrincipal; // Principal reduces by this amount
            // Wait. $balance is Total Balance.
            // New Balance = Old Balance + Period Interest - Payment.
            // If Simple, Period Interest is NOT added to Principal. It is added to "Accrued".
            // So New Balance (Total) = Old Balance + Period Interest - Payment.

            $balance = $balance + $periodInterest - $paymentAmount;

            if ($interestMode === 'simple') {
                 $currentPrincipal -= $appliedToPrincipal;
                 // Safety: Balance should match Principal + Remaining Interest
                 // But $balance calculation above does that.
            } else {
                 // Compound: Principal is Balance
                 $currentPrincipal = $balance;
            }


            // Advance Date
            $this->advanceDate($date, $modality);

            $schedule[] = [
                'period' => $period,
                'date' => $date->toDateString(),
                'installment' => round($paymentAmount, 2),
                'interest' => round($periodInterest, 2), // Show interest *accrued* this period
                'principal' => round($appliedToPrincipal, 2),
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
