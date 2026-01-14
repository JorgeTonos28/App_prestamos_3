<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->date('maturity_date')->nullable()->after('start_date');
        });

        // Populate existing loans safely using cursor to handle large datasets
        DB::table('loans')
            ->whereNotNull('target_term_periods')
            ->orderBy('id')
            ->cursor()
            ->each(function ($loan) {
                $startDate = Carbon::parse($loan->start_date);
                $periods = $loan->target_term_periods;
                $modality = $loan->modality;
                $convention = $loan->days_in_month_convention; // Defaults usually 30

                $daysToAdd = 0;
                if ($modality === 'daily') {
                    $daysToAdd = $periods * 1;
                } elseif ($modality === 'weekly') {
                    $daysToAdd = $periods * ($loan->days_in_period_weekly ?? 7);
                } elseif ($modality === 'biweekly') {
                    $daysToAdd = $periods * ($loan->days_in_period_biweekly ?? 15);
                } elseif ($modality === 'monthly') {
                    $daysToAdd = $periods * $convention;
                }

                // Calculate new date
                $maturityDate = $startDate->copy()->addDays((int)$daysToAdd);

                DB::table('loans')
                    ->where('id', $loan->id)
                    ->update(['maturity_date' => $maturityDate]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('maturity_date');
        });
    }
};
