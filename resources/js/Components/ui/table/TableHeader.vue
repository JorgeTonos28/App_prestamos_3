<script setup>
import { cn } from '@/lib/utils'

const props = defineProps({
  class: { type: String, default: '' },
})
</script>

<template>
  <thead :class="cn('bg-primary-100 [&_tr]:border-b border-primary-200 [&_tr:hover]:!bg-primary-200', props.class)">
    <slot />
  </thead>
</template>
