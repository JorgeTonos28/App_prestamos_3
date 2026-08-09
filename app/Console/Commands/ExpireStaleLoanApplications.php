<?php

namespace App\Console\Commands;

use App\Models\LoanApplication;
use App\Models\LoanApplicationEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireStaleLoanApplications extends Command
{
    protected $signature = 'whatsapp:expire-applications';

    protected $description = 'Close unfinished WhatsApp loan applications after their configured expiration date.';

    public function handle(): int
    {
        $expired = 0;

        LoanApplication::query()
            ->open()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->select('id')
            ->chunkById(100, function ($applications) use (&$expired): void {
                foreach ($applications as $application) {
                    DB::transaction(function () use ($application, &$expired): void {
                        $locked = LoanApplication::query()->lockForUpdate()->find($application->id);
                        if (! $locked || ! $locked->expires_at?->isPast() || ! $locked->isOpen()) {
                            return;
                        }

                        $fromStatus = $locked->status;
                        $locked->update([
                            'status' => LoanApplication::STATUS_EXPIRED,
                            'current_step' => 'expired',
                        ]);
                        $locked->conversation?->update([
                            'status' => 'closed',
                            'current_step' => 'expired',
                            'closed_at' => now(),
                        ]);

                        LoanApplicationEvent::create([
                            'loan_application_id' => $locked->id,
                            'actor_type' => 'system',
                            'event' => 'application_expired',
                            'from_status' => $fromStatus,
                            'to_status' => LoanApplication::STATUS_EXPIRED,
                            'metadata' => ['source' => 'scheduled_command'],
                        ]);

                        $expired++;
                    });
                }
            });

        $this->info("Expired {$expired} WhatsApp loan application(s).");

        return self::SUCCESS;
    }
}
