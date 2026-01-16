<script setup>
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    open: Boolean,
    title: { type: String, default: 'Alerta' },
    message: String
});

const emit = defineEmits(['update:open', 'confirm']);

const close = () => {
    emit('update:open', false);
    emit('confirm');
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 mb-4">
                    <i class="fa-solid fa-triangle-exclamation text-red-600 text-xl"></i>
                </div>
                <DialogTitle class="text-center text-slate-900">{{ title }}</DialogTitle>
                <DialogDescription class="text-center pt-2">
                    {{ message }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="sm:justify-center">
                <Button type="button" variant="secondary" @click="close" class="bg-slate-900 text-white hover:bg-slate-800 px-8">
                    Entendido
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
