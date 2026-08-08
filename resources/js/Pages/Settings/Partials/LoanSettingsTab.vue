<script setup>
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

defineProps({ form: { type: Object, required: true } });
</script>

<template>
    <div class="space-y-8">
        <div>
            <h3 class="text-lg font-bold text-surface-800">Préstamos, mora y pagos</h3>
            <p class="mt-1 text-sm text-surface-500">Valores predeterminados para el cálculo y los controles operativos.</p>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="global_late_fee_daily_amount">Mora diaria predeterminada (RD$)</Label>
                <Input id="global_late_fee_daily_amount" v-model="form.global_late_fee_daily_amount" type="number" min="0" step="0.01" />
            </div>
            <div class="space-y-2">
                <Label for="global_late_fee_grace_period">Días de gracia laborables</Label>
                <Input id="global_late_fee_grace_period" v-model="form.global_late_fee_grace_period" type="number" min="0" />
            </div>
            <div class="space-y-2">
                <Label for="global_late_fee_trigger_value">Cuotas vencidas para iniciar mora</Label>
                <Input id="global_late_fee_trigger_value" v-model="form.global_late_fee_trigger_value" type="number" min="0" />
            </div>
            <div class="space-y-2">
                <Label for="global_late_fee_day_type">Tipo de días para mora</Label>
                <select id="global_late_fee_day_type" v-model="form.global_late_fee_day_type" class="h-11 w-full rounded-xl border border-surface-200 bg-white px-3 text-sm">
                    <option value="business">Días laborables</option>
                    <option value="calendar">Días calendario</option>
                </select>
            </div>
            <div class="space-y-2">
                <Label for="global_late_fee_cutoff_mode">Modo de corte para mora</Label>
                <select id="global_late_fee_cutoff_mode" v-model="form.global_late_fee_cutoff_mode" class="h-11 w-full rounded-xl border border-surface-200 bg-white px-3 text-sm">
                    <option value="dynamic_payment">Dinámico por pagos</option>
                    <option value="fixed_cutoff">Fijo por fecha de corte</option>
                </select>
            </div>
            <div class="space-y-2">
                <Label for="global_payment_accrual_mode">Devengo al registrar pagos</Label>
                <select id="global_payment_accrual_mode" v-model="form.global_payment_accrual_mode" class="h-11 w-full rounded-xl border border-surface-200 bg-white px-3 text-sm">
                    <option value="realtime">En tiempo real</option>
                    <option value="cutoff_only">Sólo en cortes</option>
                </select>
            </div>
            <div class="space-y-2">
                <Label for="global_cutoff_cycle_mode">Ciclo de cortes</Label>
                <select id="global_cutoff_cycle_mode" v-model="form.global_cutoff_cycle_mode" class="h-11 w-full rounded-xl border border-surface-200 bg-white px-3 text-sm">
                    <option value="calendar">Calendario desde fecha de corte</option>
                    <option value="fixed_dates">Fechas fijas</option>
                </select>
            </div>
            <div class="space-y-2">
                <Label for="global_month_day_count_mode">Cálculo de meses</Label>
                <select id="global_month_day_count_mode" v-model="form.global_month_day_count_mode" class="h-11 w-full rounded-xl border border-surface-200 bg-white px-3 text-sm">
                    <option value="exact">Días exactos del mes</option>
                    <option value="thirty">Mes comercial (30 días)</option>
                </select>
            </div>
        </div>

        <label class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-surface-200 bg-surface-50 p-5">
            <div>
                <p class="font-semibold text-surface-800">Bloquear eliminación de pagos</p>
                <p class="mt-1 text-xs text-surface-500">Impide borrar pagos en todos los préstamos.</p>
            </div>
            <input v-model="form.disable_payment_deletion" type="checkbox" class="h-5 w-5 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
        </label>
    </div>
</template>
