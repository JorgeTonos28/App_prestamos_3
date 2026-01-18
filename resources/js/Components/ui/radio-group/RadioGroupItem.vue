<script setup>
import { computed } from "vue";
import { RadioGroupItem, RadioGroupIndicator } from "radix-vue";
import { Circle } from "lucide-vue-next";
import { cn } from "@/lib/utils";

const props = defineProps({
  id: { type: String, required: false },
  value: { type: [String, Number], required: false },
  disabled: { type: Boolean, required: false },
  required: { type: Boolean, required: false },
  name: { type: String, required: false },
  class: { type: String, required: false },
});

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;
  return delegated;
});

const forwarded = computed(() => {
  return delegatedProps.value;
});
</script>

<template>
  <RadioGroupItem
    v-bind="forwarded"
    :class="
      cn(
        'aspect-square h-4 w-4 rounded-full border border-primary text-primary ring-offset-background focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      )
    "
  >
    <RadioGroupIndicator class="flex items-center justify-center">
      <Circle class="h-2.5 w-2.5 fill-current text-current" />
    </RadioGroupIndicator>
  </RadioGroupItem>
</template>
