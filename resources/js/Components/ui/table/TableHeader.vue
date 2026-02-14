<script setup>
import { cn } from '@/lib/utils'

const props = defineProps({
  class: { type: String, default: '' },
})
</script>

<template>
  <thead :class="cn('bg-primary-600 [&_tr]:border-b border-primary-700', props.class)">
    <slot />
  </thead>
</template>
