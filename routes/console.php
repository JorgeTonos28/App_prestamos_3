<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\SendOverdueEmails;
use App\Console\Commands\SendOverdueSms;
use App\Console\Commands\UpdateLegalLoans;
use App\Console\Commands\SendAdminLoanStatusSummary;
use App\Console\Commands\RunDailyLoanAccruals;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendOverdueEmails::class)->dailyAt('08:00');
Schedule::command(SendOverdueSms::class)
    ->dailyAt(config('services.labsmobile.overdue_time', '08:05'))
    ->withoutOverlapping();
Schedule::command(UpdateLegalLoans::class)->dailyAt('07:30');
Schedule::command(SendAdminLoanStatusSummary::class)->dailyAt('08:15');

Schedule::command(RunDailyLoanAccruals::class)->dailyAt('01:00');
