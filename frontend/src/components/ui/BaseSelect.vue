<script setup lang="ts">
import { useId } from 'vue'

withDefaults(
  defineProps<{
    modelValue: string
    label?: string
    options: { value: string | number; label: string }[]
    placeholder?: string
    required?: boolean
    error?: string
  }>(),
  {
    label: '',
    placeholder: '',
    required: false,
    error: '',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
  change: [value: string]
}>()

const id = useId()

function onChange(event: Event): void {
  const value = (event.target as HTMLSelectElement).value

  emit('update:modelValue', value)
  emit('change', value)
}
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="field-label">
      {{ label }}
      <span v-if="required" class="text-rose-500">*</span>
    </label>

    <select
      :id="id"
      :value="modelValue"
      :required="required"
      class="field-input"
      :class="{ 'is-invalid': error.length > 0 }"
      @change="onChange"
    >
      <option v-if="placeholder" value="">{{ placeholder }}</option>
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>

    <p v-if="error" class="field-error">{{ error }}</p>
  </div>
</template>
