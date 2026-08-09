<script setup>
import { router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

const props = defineProps({
    form: { type: Object, required: true },
    agent: { type: Object, required: true },
});

const secretFields = [
    { key: 'whatsapp_access_token', clear: 'clear_whatsapp_access_token', label: 'Meta Access Token' },
    { key: 'whatsapp_app_secret', clear: 'clear_whatsapp_app_secret', label: 'Meta App Secret' },
    { key: 'whatsapp_verify_token', clear: 'clear_whatsapp_verify_token', label: 'Verify Token elegido por ti' },
    { key: 'openai_api_key', clear: 'clear_openai_api_key', label: 'OpenAI API Key' },
];

const toggleDocument = (key, checked) => {
    const selected = new Set(props.form.whatsapp_required_documents || []);
    checked ? selected.add(key) : selected.delete(key);
    props.form.whatsapp_required_documents = [...selected];
};

const copyWebhook = async () => navigator.clipboard.writeText(props.agent.webhook_url);
const testConnections = () => router.post(route('settings.whatsapp.test'), {}, { preserveScroll: true });
</script>

<template>
    <div class="space-y-8">
        <section class="rounded-2xl border border-surface-200 bg-surface-50 p-5">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-success-100 text-success-700">
                            <i class="fa-brands fa-whatsapp text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-surface-800">Agente de solicitudes por WhatsApp</h3>
                            <p class="text-sm text-surface-500">Recolección guiada, documentos privados y evaluación para revisión humana.</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :class="agent.enabled ? 'border-success-200 bg-success-50 text-success-700' : 'border-surface-200 bg-white text-surface-600'">
                            {{ agent.enabled ? 'Agente habilitado' : 'Agente deshabilitado' }}
                        </span>
                        <span class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :class="agent.whatsapp_ready ? 'border-success-200 bg-success-50 text-success-700' : 'border-warning-200 bg-warning-50 text-warning-700'">
                            {{ agent.whatsapp_ready ? 'Meta configurado' : 'Meta incompleto' }}
                        </span>
                        <span class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :class="agent.openai_ready ? 'border-success-200 bg-success-50 text-success-700' : 'border-warning-200 bg-warning-50 text-warning-700'">
                            {{ agent.openai_ready ? 'OpenAI configurado' : 'OpenAI incompleto' }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" class="rounded-xl" :disabled="!agent.whatsapp_ready || !agent.openai_ready" @click="testConnections">
                        <i class="fa-solid fa-plug-circle-check mr-2"></i>Probar conexiones
                    </Button>
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-surface-200 bg-white px-4 py-2.5">
                        <input v-model="form.whatsapp_agent_enabled" type="checkbox" class="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        <span class="text-sm font-semibold text-surface-700">Habilitar agente</span>
                    </label>
                </div>
            </div>
            <p v-if="form.errors.whatsapp_agent_enabled" class="mt-3 text-sm font-medium text-danger-600">{{ form.errors.whatsapp_agent_enabled }}</p>
        </section>

        <section class="rounded-2xl border border-info-200 bg-info-50 p-5 text-sm text-info-900">
            <div class="flex gap-3">
                <i class="fa-solid fa-shield-halved mt-0.5 text-info-600"></i>
                <div>
                    <p class="font-bold">Control humano obligatorio</p>
                    <p class="mt-1 leading-relaxed">El modelo interpreta respuestas y redacta el informe, pero el score se calcula con reglas locales. El agente no crea préstamos ni aprueba clientes por sí solo.</p>
                </div>
            </div>
        </section>

        <section class="space-y-5">
            <div>
                <h3 class="text-lg font-bold text-surface-800">Webhook de Meta</h3>
                <p class="text-sm text-surface-500">Copia esta URL exactamente en Meta for Developers y suscribe el campo <strong>messages</strong>.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <Input :model-value="agent.webhook_url" readonly class="font-mono text-xs" />
                <Button type="button" variant="outline" class="shrink-0 rounded-xl" @click="copyWebhook">
                    <i class="fa-regular fa-copy mr-2"></i>Copiar URL
                </Button>
            </div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <Label for="whatsapp_graph_version">Versión Graph API</Label>
                    <Input id="whatsapp_graph_version" v-model="form.whatsapp_graph_version" class="mt-2" placeholder="v23.0" />
                </div>
                <div>
                    <Label for="whatsapp_phone_number_id">Phone Number ID</Label>
                    <Input id="whatsapp_phone_number_id" v-model="form.whatsapp_phone_number_id" class="mt-2" inputmode="numeric" />
                </div>
                <div>
                    <Label for="whatsapp_business_account_id">Business Account ID</Label>
                    <Input id="whatsapp_business_account_id" v-model="form.whatsapp_business_account_id" class="mt-2" inputmode="numeric" />
                </div>
            </div>
        </section>

        <section class="space-y-5 border-t border-surface-100 pt-7">
            <div>
                <h3 class="text-lg font-bold text-surface-800">Credenciales secretas</h3>
                <p class="text-sm text-surface-500">Se cifran con APP_KEY y nunca regresan al navegador. Deja vacío para conservar el valor guardado.</p>
            </div>
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div v-for="secret in secretFields" :key="secret.key" class="rounded-xl border border-surface-200 bg-surface-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <Label :for="secret.key">{{ secret.label }}</Label>
                        <span class="text-xs font-semibold" :class="agent.secrets[secret.key] ? 'text-success-700' : 'text-warning-700'">
                            {{ agent.secrets[secret.key] ? 'Guardado' : 'Pendiente' }}
                        </span>
                    </div>
                    <Input :id="secret.key" v-model="form[secret.key]" type="password" autocomplete="new-password" class="mt-2" :placeholder="agent.secrets[secret.key] ? '•••••••••••• (sin cambios)' : 'Pegar credencial'" />
                    <label v-if="agent.secrets[secret.key]" class="mt-2 flex items-center gap-2 text-xs text-surface-500">
                        <input v-model="form[secret.clear]" type="checkbox" class="rounded border-surface-300" /> Eliminar valor guardado
                    </label>
                </div>
            </div>
        </section>

        <section class="space-y-5 border-t border-surface-100 pt-7">
            <div>
                <h3 class="text-lg font-bold text-surface-800">Modelo y comportamiento</h3>
                <p class="text-sm text-surface-500">El modelo opera sin herramientas y con salidas estructuradas.</p>
            </div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div class="md:col-span-2">
                    <Label for="openai_model">Modelo OpenAI</Label>
                    <Input id="openai_model" v-model="form.openai_model" class="mt-2 font-mono" />
                </div>
                <div>
                    <Label for="openai_reasoning_effort">Esfuerzo de razonamiento</Label>
                    <select id="openai_reasoning_effort" v-model="form.openai_reasoning_effort" class="mt-2 h-12 w-full rounded-xl border border-surface-300 bg-white px-4 text-sm">
                        <option v-for="effort in ['none', 'low', 'medium', 'high', 'xhigh', 'max']" :key="effort" :value="effort">{{ effort }}</option>
                    </select>
                </div>
            </div>
            <div>
                <Label for="whatsapp_agent_additional_instructions">Instrucciones adicionales del negocio</Label>
                <textarea id="whatsapp_agent_additional_instructions" v-model="form.whatsapp_agent_additional_instructions" rows="4" class="mt-2 w-full rounded-xl border border-surface-300 p-4 text-sm" placeholder="Políticas operativas, sin incluir secretos." />
            </div>
        </section>

        <section class="space-y-5 border-t border-surface-100 pt-7">
            <div>
                <h3 class="text-lg font-bold text-surface-800">Mensajes y plantillas</h3>
                <p class="text-sm text-surface-500">Las plantillas se usan para notificar decisiones fuera de la ventana de servicio de 24 horas.</p>
            </div>
            <div>
                <Label for="whatsapp_agent_welcome_message">Mensaje de bienvenida</Label>
                <textarea id="whatsapp_agent_welcome_message" v-model="form.whatsapp_agent_welcome_message" rows="3" class="mt-2 w-full rounded-xl border border-surface-300 p-4 text-sm" />
            </div>
            <div>
                <Label for="whatsapp_agent_privacy_notice">Aviso de privacidad y consentimiento</Label>
                <textarea id="whatsapp_agent_privacy_notice" v-model="form.whatsapp_agent_privacy_notice" rows="4" class="mt-2 w-full rounded-xl border border-surface-300 p-4 text-sm" />
            </div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div><Label for="whatsapp_approval_template">Plantilla de aprobación</Label><Input id="whatsapp_approval_template" v-model="form.whatsapp_approval_template" class="mt-2 font-mono" placeholder="loan_application_approved" /></div>
                <div><Label for="whatsapp_rejection_template">Plantilla de rechazo</Label><Input id="whatsapp_rejection_template" v-model="form.whatsapp_rejection_template" class="mt-2 font-mono" placeholder="loan_application_rejected" /></div>
                <div><Label for="whatsapp_template_language">Idioma</Label><Input id="whatsapp_template_language" v-model="form.whatsapp_template_language" class="mt-2 font-mono" /></div>
            </div>
        </section>

        <section class="space-y-5 border-t border-surface-100 pt-7">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-surface-800">Documentos requeridos</h3>
                    <p class="text-sm text-surface-500">PDF, JPG y PNG en almacenamiento privado.</p>
                </div>
                <div class="w-full sm:w-44">
                    <Label for="whatsapp_max_document_mb">Máximo (MB)</Label>
                    <Input id="whatsapp_max_document_mb" v-model="form.whatsapp_max_document_mb" type="number" min="1" max="25" class="mt-2" />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <label v-for="document in agent.document_catalog" :key="document.key" class="flex cursor-pointer items-center gap-3 rounded-xl border border-surface-200 bg-white p-4 hover:border-primary-300 hover:bg-primary-50/30">
                    <input type="checkbox" class="h-4 w-4 rounded border-surface-300 text-primary-600" :checked="form.whatsapp_required_documents.includes(document.key)" @change="toggleDocument(document.key, $event.target.checked)" />
                    <span class="text-sm font-medium text-surface-700">{{ document.label }}</span>
                </label>
            </div>
        </section>

        <section class="space-y-5 border-t border-surface-100 pt-7">
            <div>
                <h3 class="text-lg font-bold text-surface-800">Política de riesgo</h3>
                <p class="text-sm text-surface-500">Un score mayor implica más riesgo. Estos umbrales controlan el cálculo local.</p>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><Label>Máximo riesgo bajo</Label><Input v-model="form.risk_low_max_score" type="number" min="0" max="99" class="mt-2" /></div>
                <div><Label>Máximo riesgo medio</Label><Input v-model="form.risk_medium_max_score" type="number" min="1" max="100" class="mt-2" /></div>
                <div><Label>Deuda / ingreso máxima</Label><Input v-model="form.risk_max_debt_to_income" type="number" min="0.01" max="2" step="0.01" class="mt-2" /></div>
                <div><Label>Cuota / ingreso máxima</Label><Input v-model="form.risk_max_installment_to_income" type="number" min="0.01" max="2" step="0.01" class="mt-2" /></div>
                <div><Label>Préstamo / ingreso</Label><Input v-model="form.risk_max_loan_to_monthly_income" type="number" min="0.1" max="120" step="0.1" class="mt-2" /></div>
                <div><Label>Ingreso mínimo</Label><Input v-model="form.risk_min_monthly_income" type="number" min="0" class="mt-2" /></div>
                <div><Label>Antigüedad mínima</Label><Input v-model="form.risk_min_employment_months" type="number" min="0" max="720" class="mt-2" /></div>
                <div><Label>Vencimiento (días)</Label><Input v-model="form.whatsapp_application_expiry_days" type="number" min="1" max="365" class="mt-2" /></div>
            </div>
            <div>
                <Label for="risk_policy_notes">Notas de política para el informe</Label>
                <textarea id="risk_policy_notes" v-model="form.risk_policy_notes" rows="4" class="mt-2 w-full rounded-xl border border-surface-300 p-4 text-sm" />
            </div>
            <label class="flex items-start gap-3 rounded-xl border border-surface-200 bg-surface-50 p-4">
                <input v-model="form.whatsapp_auto_create_client" type="checkbox" class="mt-1 h-4 w-4 rounded border-surface-300 text-primary-600" />
                <span>
                    <span class="block text-sm font-semibold text-surface-800">Proponer crear cliente al aprobar</span>
                    <span class="block text-xs text-surface-500">El administrador lo confirma en la decisión; nunca ocurre antes de aprobar.</span>
                </span>
            </label>
        </section>
    </div>
</template>
