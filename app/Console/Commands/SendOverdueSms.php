<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Setting;
use App\Models\SmsNotification;
use App\Services\ArrearsCalculator;
use App\Services\SmsDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOverdueSms extends Command
{
    protected $signature = 'loans:send-overdue-sms {--dry-run : Calculate recipients and messages without calling LabsMobile} {--force : Ignore configured send time for manual execution}';

    protected $description = 'Sends configurable SMS reminders to clients with overdue active loans';

    public function handle(ArrearsCalculator $calculator, SmsDispatchService $dispatcher): int
    {
        if (! config('services.labsmobile.enabled', false)) {
            $this->warn('LabsMobile SMS is disabled. Set LABSMOBILE_ENABLED=true to enable this command.');

            return self::SUCCESS;
        }

        $settings = Setting::whereIn('key', [
            'overdue_sms_enabled',
            'overdue_sms_send_time',
            'overdue_sms_interval_days',
            'overdue_sms_messages_per_day',
            'overdue_sms_body',
        ])->pluck('value', 'key');

        if (($settings['overdue_sms_enabled'] ?? '0') !== '1') {
            $this->info('Overdue SMS reminders are disabled by administrator settings.');

            return self::SUCCESS;
        }

        $sendTime = $settings['overdue_sms_send_time'] ?? '08:05';
        if (! $this->option('force') && now()->format('H:i') !== $sendTime) {
            return self::SUCCESS;
        }

        $intervalDays = max(1, (int) ($settings['overdue_sms_interval_days'] ?? 1));
        $messagesPerDay = min(5, max(1, (int) ($settings['overdue_sms_messages_per_day'] ?? 1)));
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
            $daysOverdue = (int) ($arrears['days'] ?? 0);

            if (($arrears['amount'] ?? 0) <= 0 || $daysOverdue < 1) {
                continue;
            }

            if ((($daysOverdue - 1) % $intervalDays) !== 0) {
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

            $alreadyToday = SmsNotification::where('client_id', $client->id)
                ->where('provider', 'labsmobile')
                ->where('source', 'overdue')
                ->whereDate('notification_date', $today)
                ->count();

            if ($alreadyToday >= $messagesPerDay) {
                $skipped++;

                continue;
            }

            $totalDue = $clientItems->sum(fn (array $item) => (float) ($item['arrears']['display_total_due'] ?? $item['arrears']['total_due'] ?? $item['arrears']['amount'] ?? 0));
            $maxDays = (int) $clientItems->max(fn (array $item) => (int) ($item['arrears']['days'] ?? 0));
            $message = $this->buildMessage(
                $settings['overdue_sms_body'] ?? null,
                $client->first_name,
                $client->first_name.' '.$client->last_name,
                $totalDue,
                $maxDays,
                $clientItems->count()
            );

            for ($sequence = $alreadyToday + 1; $sequence <= $messagesPerDay; $sequence++) {
                if ($this->option('dry-run')) {
                    $this->line("#{$sequence} | {$client->first_name} {$client->last_name} | {$client->phone} | {$message}");

                    continue;
                }

                try {
                    $dispatcher->send(
                        $client,
                        $message,
                        $clientItems->count() === 1 ? $first['loan'] : null,
                        'overdue',
                        null,
                    );
                    $sent++;
                } catch (Throwable $e) {
                    Log::error('Failed to send overdue SMS', [
                        'client_id' => $client->id,
                        'sequence' => $sequence,
                        'error' => $e->getMessage(),
                    ]);
                    $failed++;
                    break;
                }
            }
        }

        $this->info("Finished SMS run. Sent/simulated: {$sent}; skipped: {$skipped}; failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function buildMessage(?string $customTemplate, string $firstName, string $fullName, float $amountDue, int $daysOverdue, int $loanCount): string
    {
        // Keep the default template short and GSM-friendly. In particular,
        // "dias" avoids forcing UCS-2 just because of the accented i.
        $template = $customTemplate
            ?: 'Hola {client_first_name}. Tiene RD${amount_due} vencidos y {days_overdue} dias de atraso. Favor regularizar su pago. Gracias.';

        return strtr($template, [
            '{client_first_name}' => $firstName,
            '{client_name}' => $fullName,
            '{amount_due}' => number_format($amountDue, 2, '.', ','),
            '{days_overdue}' => (string) $daysOverdue,
            '{loan_count}' => (string) $loanCount,
        ]);
    }
}
