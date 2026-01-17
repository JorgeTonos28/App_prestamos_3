<?php

require 'vendor/autoload.php';

use App\Services\AmortizationService;
use Carbon\Carbon;

// Mock FinancialHelper for the script
class FinancialHelper
{
    public static function diffInDays($start, $end, $convention = 30)
    {
        return 30; // Mock 30 days for simplicity
    }
}

$service = new AmortizationService();

$principal = 50000;
$monthlyRate = 15;
$modality = 'monthly';
$installment = 12500;
$startDate = '2025-12-17';
$interestMode = 'simple';
$daysInMonthConvention = 30;

$schedule = $service->generateSchedule(
    $principal,
    $monthlyRate,
    $modality,
    $installment,
    $startDate,
    $interestMode,
    $daysInMonthConvention
);

echo "Total Installments: " . count($schedule) . "\n";
echo str_pad("#", 5) . str_pad("Date", 12) . str_pad("Payment", 12) . str_pad("Interest", 12) . str_pad("Capital", 12) . str_pad("Balance", 12) . "\n";
foreach ($schedule as $row) {
    echo str_pad($row['period'], 5) .
         str_pad($row['date'], 12) .
         str_pad($row['installment'], 12) .
         str_pad($row['interest'], 12) .
         str_pad($row['principal'], 12) .
         str_pad($row['balance'], 12) . "\n";
}
