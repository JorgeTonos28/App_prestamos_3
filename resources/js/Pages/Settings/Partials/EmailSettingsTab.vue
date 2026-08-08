<script setup>
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

defineProps({ form: { type: Object, required: true } });
</script>

<template>
    <div class="space-y-8">
        <div>
            <h3 class="text-lg font-bold text-surface-800">Correo y reportes</h3>
            <p class="mt-1 text-sm text-surface-500">Remitente de cobranza y destino de los resúmenes administrativos.</p>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="email_sender_name">Nombre del remitente</Label>
                <Input id="email_sender_name" v-model="form.email_sender_name" />
            </div>
            <div class="space-y-2">
                <Label for="email_sender_address">Correo del remitente</Label>
                <Input id="email_sender_address" v-model="form.email_sender_address" type="email" />
            </div>
            <div class="space-y-2 md:col-span-2">
                <Label for="admin_notification_email">Correo del administrador</Label>
                <Input id="admin_notification_email" v-model="form.admin_notification_email" type="email" placeholder="admin@empresa.com" class="max-w-md" />
            </div>
        </div>

        <div class="space-y-5 border-t border-surface-100 pt-8">
            <div class="space-y-2">
                <Label for="overdue_email_subject">Asunto del correo de mora</Label>
                <Input id="overdue_email_subject" v-model="form.overdue_email_subject" />
            </div>
            <div class="space-y-2">
                <Label for="overdue_email_body">Cuerpo del correo</Label>
                <textarea id="overdue_email_body" v-model="form.overdue_email_body" rows="7" class="w-full rounded-xl border border-surface-200 bg-white px-3 py-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"></textarea>
                <p class="text-xs text-surface-500">Variables: {client_name}, {amount_due}, {days_overdue}</p>
            </div>
        </div>
    </div>
</template>
