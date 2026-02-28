<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('client_code', 20)->nullable()->after('id')->unique();
        });

        $testNationalIds = [
            '001-0000001-1',
            '001-0000002-2',
            '001-0000003-3',
        ];

        $normalCounter = 1;
        $testCounter = 1;

        DB::table('clients')
            ->select('id', 'national_id')
            ->orderBy('id')
            ->get()
            ->each(function ($client) use (&$normalCounter, &$testCounter, $testNationalIds) {
                $isTestClient = in_array((string) $client->national_id, $testNationalIds, true);

                $clientCode = $isTestClient
                    ? 'P' . str_pad((string) $testCounter++, 3, '0', STR_PAD_LEFT)
                    : str_pad((string) $normalCounter++, 3, '0', STR_PAD_LEFT);

                DB::table('clients')
                    ->where('id', $client->id)
                    ->update([
                        'client_code' => $clientCode,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['client_code']);
            $table->dropColumn('client_code');
        });
    }
};
