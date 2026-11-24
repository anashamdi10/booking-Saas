<script setup lang="ts">
withDefaults(
  defineProps<{
    title?: string
    description?: string
  }>(),
  { title: 'لا توجد بيانات', description: '' },
)
</script>

<template>
  <div class="surface-card grid place-items-center gap-2 px-6 py-14 text-center">
    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-2xl" aria-hidden="true">
      ⌀
    </span>
    <p class="font-bold text-slate-800">{{ title }}</p>
    <p v-if="description" class="max-w-md text-sm text-slate-500">{{ description }}</p>
  </div>
</template>
