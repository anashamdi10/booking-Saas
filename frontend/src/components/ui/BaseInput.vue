<script setup lang="ts">
import { computed, useId } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    label?: string
    type?: string
    placeholder?: string
    required?: boolean
    autocomplete?: string
    error?: string
    hint?: string
    min?: string | number
    max?: string | number
  }>(),
  {
    label: '',
    type: 'text',
    placeholder: '',
    required: false,
    autocomplete: 'off',
    error: '',
    hint: '',
    min: undefined,
    max: undefined,
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const id = useId()
const hasError = computed(() => props.error.length > 0)

function onInput(event: Event): void {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="field-label">
      {{ label }}
      <span v-if="required" class="text-rose-500">*</span>
    </label>

    <input
      :id="id"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :required="required"
      :autocomplete="autocomplete"
      :min="min"
      :max="max"
      :aria-invalid="hasError"
      :aria-describedby="hasError ? `${id}-error` : undefined"
      class="field-input"
      :class="{ 'is-invalid': hasError }"
      @input="onInput"
    />

    <p v-if="hasError" :id="`${id}-error`" class="field-error">{{ error }}</p>
    <p v-else-if="hint" class="mt-1 text-xs text-slate-400">{{ hint }}</p>
  </div>
</template>
