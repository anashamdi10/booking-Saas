<script setup lang="ts">
withDefaults(
  defineProps<{
    type?: 'success' | 'error' | 'info' | 'warning'
    message?: string
  }>(),
  { type: 'info', message: '' },
)

const styles = {
  success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
  error: 'border-rose-200 bg-rose-50 text-rose-800',
  info: 'border-sky-200 bg-sky-50 text-sky-800',
  warning: 'border-amber-200 bg-amber-50 text-amber-800',
}

const icons = {
  success: '✓',
  error: '✕',
  info: 'ℹ',
  warning: '!',
}
</script>

<template>
  <div
    class="flex items-start gap-2 rounded-xl border px-4 py-3 text-sm font-medium"
    :class="styles[type]"
    role="alert"
  >
    <span aria-hidden="true">{{ icons[type] }}</span>
    <p>{{ message }}</p>
  </div>
</template>
