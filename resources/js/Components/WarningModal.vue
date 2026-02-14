<script setup>
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    open: Boolean,
    title: {
        type: String,
        default: 'Atención'
    },
    message: {
        type: String,
        default: ''
    },
    confirmText: {
        type: String,
        default: null
    },
    cancelText: {
        type: String,
        default: 'Cancelar'
    }
});

const emit = defineEmits(['update:open', 'confirm']);

const close = () => {
    emit('update:open', false);
};

const confirm = () => {
    emit('confirm');
    close();
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md bg-white">
            <DialogHeader>
                <DialogTitle class="text-amber-600 flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    {{ title }}
                </DialogTitle>
                <DialogDescription class="pt-2 text-surface-600">
                    {{ message }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="sm:justify-end gap-2">
                <Button type="button" variant="secondary" @click="close" class="bg-surface-100 hover:bg-surface-200 text-surface-800">
                    {{ confirmText ? cancelText : 'Entendido' }}
                </Button>
                <Button v-if="confirmText" type="button" @click="confirm" class="bg-red-600 hover:bg-red-700 text-white shadow-sm">
                    {{ confirmText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
