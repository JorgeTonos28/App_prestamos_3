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
    }
});

const emit = defineEmits(['update:open']);

const close = () => {
    emit('update:open', false);
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
                <DialogDescription class="pt-2 text-slate-600">
                    {{ message }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="sm:justify-end">
                <Button type="button" variant="secondary" @click="close" class="bg-slate-100 hover:bg-slate-200 text-slate-800">
                    Entendido
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
