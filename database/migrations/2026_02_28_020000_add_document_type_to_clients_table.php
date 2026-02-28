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
            $table->enum('document_type', ['cedula', 'passport'])
                ->default('cedula')
                ->after('client_code');
        });

        DB::table('clients')
            ->select('id', 'national_id')
            ->orderBy('id')
            ->get()
            ->each(function ($client) {
                $isCedula = preg_match('/^\d{3}-\d{7}-\d{1}$/', (string) $client->national_id) === 1;

                DB::table('clients')
                    ->where('id', $client->id)
                    ->update([
                        'document_type' => $isCedula ? 'cedula' : 'passport',
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }
};
