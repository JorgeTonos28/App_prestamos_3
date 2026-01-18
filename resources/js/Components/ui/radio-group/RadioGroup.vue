<script setup>
import { computed } from "vue";
import { RadioGroupRoot } from "radix-vue";
import { cn } from "@/lib/utils";

const props = defineProps({
  modelValue: { type: [String, Number], required: false },
  defaultValue: { type: [String, Number], required: false },
  disabled: { type: Boolean, required: false },
  name: { type: String, required: false },
  required: { type: Boolean, required: false },
  orientation: { type: String, required: false },
  dir: { type: String, required: false },
  loop: { type: Boolean, required: false },
  class: { type: String, required: false },
});

const emits = defineEmits(["update:modelValue"]);

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;
  return delegated;
});

const forwarded = computed(() => {
  return delegatedProps.value;
});
</script>

<template>
  <RadioGroupRoot
    :class="cn('grid gap-2', props.class)"
    v-bind="forwarded"
    @update:model-value="emits('update:modelValue', $event)"
  >
    <slot />
  </RadioGroupRoot>
</template>
