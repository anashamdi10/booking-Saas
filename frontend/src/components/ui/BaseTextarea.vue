<script setup lang="ts">
import { useId } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    label?: string
    placeholder?: string
    rows?: number
    required?: boolean
    error?: string
    maxlength?: number
  }>(),
  {
    label: '',
    placeholder: '',
    rows: 4,
    required: false,
    error: '',
    maxlength: undefined,
  },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const id = useId()

function onInput(event: Event): void {
  emit('update:modelValue', (event.target as HTMLTextAreaElement).value)
}
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="field-label">
      {{ label }}
      <span v-if="required" class="text-rose-500">*</span>
    </label>

    <textarea
      :id="id"
      :value="modelValue"
      :rows="rows"
      :placeholder="placeholder"
      :required="required"
      :maxlength="maxlength"
      class="field-input resize-y"
      :class="{ 'is-invalid': props.error.length > 0 }"
      @input="onInput"
    />

    <p v-if="props.error" class="field-error">{{ props.error }}</p>
  </div>
</template>
