<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\ArrearsCalculator;
use App\Mail\OverdueLoanMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendOverdueEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:send-overdue-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends email notifications to clients with overdue loans';

    /**
     * Execute the console command.
     */
    public function handle(ArrearsCalculator $calculator)
    {
        $this->info('Starting overdue email check...');

        $activeLoans = Loan::where('status', 'active')->with('client', 'ledgerEntries')->get();
        $count = 0;

        foreach ($activeLoans as $loan) {
            $arrears = $calculator->calculate($loan);

            if ($arrears['amount'] > 0) {
                // Client must have an email
                if ($loan->client && $loan->client->email) {

                    try {
                        Mail::to($loan->client->email)->queue(new OverdueLoanMail($loan, $arrears));
                        $this->info("Email sent to {$loan->client->email} (Loan {$loan->code})");
                        $count++;
                    } catch (\Exception $e) {
                        $this->error("Failed to send email to {$loan->client->email}: " . $e->getMessage());
                        Log::error("Failed to send overdue email: " . $e->getMessage());
                    }
                }
            }
        }

        $this->info("Finished. Sent $count emails.");
    }
}
