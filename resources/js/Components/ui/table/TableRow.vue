<script setup>
import { cn } from '@/lib/utils'

const props = defineProps({
  class: { type: String, default: '' },
})
</script>

<template>
  <tr
    :class="cn(
      'border-b border-surface-200 transition-colors hover:bg-primary-50 data-[state=selected]:bg-primary-100',
      props.class
    )"
  >
    <slot />
  </tr>
</template>
