<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="border-gray-300 focus:border-primary-500 focus:ring-primary-500 rounded-xl shadow-sm transition-all duration-200 py-3 px-4"
        v-model="model"
        ref="input"
    />
</template>
