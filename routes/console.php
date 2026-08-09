<?php

use App\Console\Commands\ExpireStaleLoanApplications;
use App\Console\Commands\RunDailyLoanAccruals;
use App\Console\Commands\SendAdminLoanStatusSummary;
use App\Console\Commands\SendOverdueEmails;
use App\Console\Commands\SendOverdueSms;
use App\Console\Commands\UpdateLegalLoans;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendOverdueEmails::class)->dailyAt('08:00');
// Runs every minute, but SendOverdueSms exits immediately unless the current
// time matches the administrator-configured overdue_sms_send_time.
Schedule::command(SendOverdueSms::class)
    ->everyMinute()
    ->withoutOverlapping();
Schedule::command(UpdateLegalLoans::class)->dailyAt('07:30');
Schedule::command(SendAdminLoanStatusSummary::class)->dailyAt('08:15');

Schedule::command(RunDailyLoanAccruals::class)->dailyAt('01:00');

Schedule::command(ExpireStaleLoanApplications::class)
    ->hourly()
    ->withoutOverlapping();
