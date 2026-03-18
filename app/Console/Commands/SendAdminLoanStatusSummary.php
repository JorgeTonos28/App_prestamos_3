<?php

namespace App\Console\Commands;

use App\Mail\AdminLoanStatusSummaryMail;
use App\Models\Loan;
use App\Models\Setting;
use App\Services\ArrearsCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendAdminLoanStatusSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:send-admin-status-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends daily summary email to the administrator with overdue and legal loans.';

    public function handle(ArrearsCalculator $calculator): int
    {
        $adminEmail = Setting::where('key', 'admin_notification_email')->value('value');

        if (!$adminEmail) {
            $this->warn('Admin notification email not configured. Skipping.');
            return Command::SUCCESS;
        }

        $activeLoans = Loan::where('status', 'active')
            ->where('is_archived', false)
            ->with('client', 'ledgerEntries')
            ->get();

        $overdueLoans = [];
        $legalLoans = [];

        foreach ($activeLoans as $loan) {
            $arrears = $calculator->calculate($loan);

            if (($arrears['amount'] ?? 0) > 0) {
                $overdueLoans[] = [
                    'code' => $loan->code,
                    'client' => $loan->client ? ($loan->client->first_name . ' ' . $loan->client->last_name) : 'Cliente desconocido',
                    'days' => $arrears['days'] ?? 0,
                    'amount' => (float) ($arrears['amount'] ?? 0),
                    'balance' => (float) $loan->balance_total,
                ];
            }

            if ($loan->legal_status) {
                $legalFees = $loan->ledgerEntries->where('type', 'legal_fee')->sum('amount');
                $legalLoans[] = [
                    'code' => $loan->code,
                    'client' => $loan->client ? ($loan->client->first_name . ' ' . $loan->client->last_name) : 'Cliente desconocido',
                    'entered_at' => $loan->legal_entered_at?->format('Y-m-d') ?? 'N/A',
                    'legal_fees' => (float) $legalFees,
                    'balance' => (float) $loan->balance_total,
                ];
            }
        }

        try {
            Mail::to($adminEmail)->queue(new AdminLoanStatusSummaryMail($overdueLoans, $legalLoans));
            $this->info("Summary sent to {$adminEmail}.");
        } catch (\Throwable $e) {
            Log::error('Failed to send admin status summary', ['error' => $e->getMessage()]);
        }

        return Command::SUCCESS;
    }
}
