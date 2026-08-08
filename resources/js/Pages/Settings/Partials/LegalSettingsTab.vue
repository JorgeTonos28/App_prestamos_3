<script setup>
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

defineProps({ form: { type: Object, required: true } });
</script>

<template>
    <div class="space-y-8">
        <div>
            <h3 class="text-lg font-bold text-surface-800">Gestión legal</h3>
            <p class="mt-1 text-sm text-surface-500">Costes, umbral de entrada y modelo utilizado para los documentos legales.</p>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <div class="space-y-2">
                <Label for="legal_fee_default_amount">Costo legal predeterminado (RD$)</Label>
                <Input id="legal_fee_default_amount" v-model="form.legal_fee_default_amount" type="number" min="0" step="0.01" />
            </div>
            <div class="space-y-2">
                <Label for="legal_entry_fee_default">Costo de entrada a legal (RD$)</Label>
                <Input id="legal_entry_fee_default" v-model="form.legal_entry_fee_default" type="number" min="0" step="0.01" />
            </div>
            <div class="space-y-2">
                <Label for="legal_days_overdue_threshold">Días de mora para pasar a legal</Label>
                <Input id="legal_days_overdue_threshold" v-model="form.legal_days_overdue_threshold" type="number" min="0" />
            </div>
        </div>

        <div class="space-y-2">
            <Label for="legal_contract_template">Modelo de contrato</Label>
            <textarea id="legal_contract_template" v-model="form.legal_contract_template" rows="12" class="w-full rounded-xl border border-surface-200 bg-white px-3 py-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" placeholder="Use marcadores como {client_name}, {loan_code} y {loan_amount}."></textarea>
            <p class="text-xs leading-relaxed text-surface-500">Marcadores: {client_name}, {client_national_id}, {client_address}, {client_phone}, {client_email}, {loan_code}, {loan_start_date}, {loan_amount}, {legal_fee_amount}, {today_date}.</p>
        </div>
    </div>
</template>
