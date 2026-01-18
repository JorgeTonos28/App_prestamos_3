<script setup>
import { computed } from "vue";
import { CheckboxRoot, CheckboxIndicator } from "radix-vue";
import { cn } from "@/lib/utils";
import { Check } from "lucide-vue-next";

const props = defineProps({
  checked: { type: [Boolean, String], required: false },
  defaultChecked: { type: Boolean, required: false },
  required: { type: Boolean, required: false },
  disabled: { type: Boolean, required: false },
  name: { type: String, required: false },
  value: { type: String, required: false },
  id: { type: String, required: false },
  class: { type: String, required: false },
});

const emits = defineEmits(["update:checked"]);

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;
  return delegated;
});

const forwarded = computed(() => {
  return delegatedProps.value;
});
</script>

<template>
  <CheckboxRoot
    v-bind="forwarded"
    :class="
      cn(
        'peer h-4 w-4 shrink-0 rounded-sm border border-primary ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground',
        props.class,
      )
    "
    @update:checked="emits('update:checked', $event)"
  >
    <CheckboxIndicator
      class="flex h-full w-full items-center justify-center text-current"
    >
      <slot>
        <Check class="h-3.5 w-3.5" />
      </slot>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
