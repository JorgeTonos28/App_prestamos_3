<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class LegalFeeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        Setting::firstOrCreate(
            ['key' => 'legal_fee_default_amount'],
            ['value' => '1000.00']
        );

        Setting::firstOrCreate(
            ['key' => 'legal_contract_template'],
            ['value' => "CONTRATO DE PRÉSTAMO\n\nCliente: {client_name}\nCédula: {client_national_id}\nDirección: {client_address}\nTeléfono: {client_phone}\nCorreo: {client_email}\n\nPréstamo: {loan_code}\nFecha de inicio: {loan_start_date}\nMonto principal: {loan_amount}\nGastos legales: {legal_fee_amount}\n\nFecha de generación: {today_date}\n"]
        );
    }
}
