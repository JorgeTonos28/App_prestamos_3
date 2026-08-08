<?php

namespace App\Console\Commands;

use App\Services\LabsMobileSmsService;
use Illuminate\Console\Command;
use Throwable;

class SendLabsMobileTestSms extends Command
{
    protected $signature = 'labsmobile:send-test
        {phone : Dominican phone number, with or without the country code 1}
        {--message= : Text to submit to LabsMobile in simulated mode}';

    protected $description = 'Submit a no-cost simulated SMS to LabsMobile to verify the local integration';

    public function handle(LabsMobileSmsService $sms): int
    {
        if (! config('services.labsmobile.test_mode', true)) {
            $this->error('Refusing to send: LABSMOBILE_TEST_MODE must be true for this command.');

            return self::FAILURE;
        }

        $message = (string) ($this->option('message') ?: 'Prueba de integración LabsMobile desde App Presto.');

        try {
            $result = $sms->send((string) $this->argument('phone'), $message);
        } catch (Throwable $e) {
            $this->error('LabsMobile test failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Simulated SMS accepted by LabsMobile. No SMS was delivered and no credit was consumed.');

        if (! empty($result['subid'])) {
            $this->line('LabsMobile reference: '.$result['subid']);
        }

        return self::SUCCESS;
    }
}
