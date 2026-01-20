<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Dialog from '@/Components/ui/dialog/Dialog.vue';
import DialogContent from '@/Components/ui/dialog/DialogContent.vue';
import DialogHeader from '@/Components/ui/dialog/DialogHeader.vue';
import DialogTitle from '@/Components/ui/dialog/DialogTitle.vue';
import DialogDescription from '@/Components/ui/dialog/DialogDescription.vue';
import DialogFooter from '@/Components/ui/dialog/DialogFooter.vue';
import Button from '@/Components/ui/button/Button.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Textarea } from '@/Components/ui/textarea';
import { computed } from 'vue';

const props = defineProps({
    loan: Object,
    show: Boolean,
});

const emit = defineEmits(['close']);

const form = useForm({
    reason: '',
});

const submit = () => {
    form.post(route('loans.cancel', props.loan.id), {
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
};

const isWriteOff = computed(() => props.loan.payments_count > 0);
const title = computed(() => isWriteOff.value ? 'Marcar como Incobrable' : 'Cancelar Préstamo');
const description = computed(() => isWriteOff.value
    ? 'Esta acción marcará el préstamo como incobrable y detendrá la acumulación de intereses. El saldo se ajustará a cero.'
    : 'Esta acción cancelará el préstamo. Utilice esto solo si el préstamo fue creado por error y no ha tenido actividad.');

</script>

<template>
    <Dialog :open="show" @update:open="$emit('close')">
        <DialogContent class="sm:max-w-[425px] bg-white">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4 py-4">
                <div class="space-y-2">
                    <InputLabel for="reason" value="Motivo / Nota" />
                    <Textarea
                        id="reason"
                        v-model="form.reason"
                        placeholder="Explique la razón..."
                        class="resize-none"
                        rows="4"
                    />
                    <InputError :message="form.errors.reason" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="$emit('close')">
                        Cancelar
                    </Button>
                    <Button type="submit" variant="destructive" :disabled="form.processing">
                        {{ isWriteOff ? 'Confirmar Incobrable' : 'Confirmar Cancelación' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
