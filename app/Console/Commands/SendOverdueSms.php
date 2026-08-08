<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Setting;
use App\Models\SmsNotification;
use App\Services\ArrearsCalculator;
use App\Services\LabsMobileSmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOverdueSms extends Command
{
    protected $signature = 'loans:send-overdue-sms {--dry-run : Calculate recipients and messages without calling LabsMobile}';

    protected $description = 'Sends one daily SMS per client with overdue active loans';

    public function handle(ArrearsCalculator $calculator, LabsMobileSmsService $sms): int
    {
        if (! config('services.labsmobile.enabled', false)) {
            $this->warn('LabsMobile SMS is disabled. Set LABSMOBILE_ENABLED=true to enable this command.');
            return self::SUCCESS;
        }

        $today = now()->toDateString();
        $loans = Loan::where('status', 'active')
            ->where('is_archived', false)
            ->with(['client', 'ledgerEntries'])
            ->get();

        $overdueByClient = collect();

        foreach ($loans as $loan) {
            if (! $loan->client?->phone) {
                continue;
            }

            $arrears = $calculator->calculate($loan);
            if (($arrears['amount'] ?? 0) <= 0) {
                continue;
            }

            $overdueByClient->push([
                'loan' => $loan,
                'arrears' => $arrears,
            ]);
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($overdueByClient->groupBy(fn (array $item) => $item['loan']->client_id) as $clientItems) {
            /** @var Collection<int, array{loan: Loan, arrears: array}> $clientItems */
            $first = $clientItems->first();
            $client = $first['loan']->client;

            if (SmsNotification::where('client_id', $client->id)
                ->where('provider', 'labsmobile')
                ->whereDate('notification_date', $today)
                ->exists()) {
                $skipped++;
                continue;
            }

            $totalDue = $clientItems->sum(fn (array $item) => (float) ($item['arrears']['display_total_due'] ?? $item['arrears']['total_due'] ?? $item['arrears']['amount'] ?? 0));
            $maxDays = (int) $clientItems->max(fn (array $item) => (int) ($item['arrears']['days'] ?? 0));
            $message = $this->buildMessage($client->first_name, $client->first_name.' '.$client->last_name, $totalDue, $maxDays, $clientItems->count());

            if ($this->option('dry-run')) {
                $this->line("{$client->first_name} {$client->last_name} | {$client->phone} | {$message}");
                $skipped++;
                continue;
            }

            $notification = SmsNotification::create([
                'client_id' => $client->id,
                'loan_id' => $clientItems->count() === 1 ? $first['loan']->id : null,
                'phone' => $client->phone,
                'message' => $message,
                'provider' => 'labsmobile',
                'status' => 'pending',
                'notification_date' => $today,
            ]);

            try {
                $result = $sms->send($client->phone, $message);
                $isTest = config('services.labsmobile.test_mode', true);

                $notification->update([
                    'provider_subid' => $result['subid'] ?? null,
                    'api_code' => (string) ($result['code'] ?? '0'),
                    'status' => $isTest ? 'simulated' : 'accepted',
                    'sent_at' => now(),
                ]);

                $sent++;
            } catch (Throwable $e) {
                $notification->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                Log::error('Failed to send overdue SMS', [
                    'client_id' => $client->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->info("Finished SMS run. Sent/simulated: {$sent}; skipped: {$skipped}; failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function buildMessage(string $firstName, string $fullName, float $amountDue, int $daysOverdue, int $loanCount): string
    {
        $template = Setting::where('key', 'overdue_sms_body')->value('value')
            ?? 'Hola {client_first_name}. Le recordamos que presenta un monto vencido de RD${amount_due} con {days_overdue} días de atraso. Favor regularizar su pago. Gracias.';

        return strtr($template, [
            '{client_first_name}' => $firstName,
            '{client_name}' => $fullName,
            '{amount_due}' => number_format($amountDue, 2, '.', ','),
            '{days_overdue}' => (string) $daysOverdue,
            '{loan_count}' => (string) $loanCount,
        ]);
    }
}
