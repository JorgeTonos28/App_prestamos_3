<?php

use App\Services\AmortizationService;
use Carbon\Carbon;

require __DIR__.'/bootstrap/app.php';

$service = new AmortizationService();

echo "Running Amortization Debug...\n";
$schedule = $service->generateSchedule(
    principal: 50000,
    monthlyRate: 15,
    modality: 'monthly',
    installmentAmount: 12500,
    startDate: '2025-07-04',
    interestMode: 'simple',
    daysInMonthConvention: 30
);

echo "Total Periods: " . count($schedule) . "\n";
foreach ($schedule as $row) {
    echo "Period {$row['period']} ({$row['date']}): Inst={$row['installment']}, Int={$row['interest']}, Princ={$row['principal']}, Bal={$row['balance']}\n";
}
