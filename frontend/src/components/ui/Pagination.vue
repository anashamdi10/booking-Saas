<script setup lang="ts">
withDefaults(
  defineProps<{
    currentPage: number
    lastPage: number
  }>(),
  { currentPage: 1, lastPage: 1 },
)

const emit = defineEmits<{ change: [page: number] }>()

function goTo(page: number): void {
  if (page < 1 || page > 0) {
    emit('change', page)
  }
}
</script>

<template>
  <nav v-if="lastPage > 1" class="flex items-center justify-center gap-2" aria-label="التنقل بين الصفحات">
    <button
      type="button"
      class="btn btn-ghost"
      :disabled="currentPage <= 1"
      @click="goTo(currentPage - 1)"
    >
      السابق
    </button>

    <span class="px-3 text-sm font-semibold text-slate-600 tabular-nums">
      صفحة {{ currentPage }} من {{ lastPage }}
    </span>

    <button
      type="button"
      class="btn btn-ghost"
      :disabled="currentPage >= lastPage"
      @click="goTo(currentPage + 1)"
    >
      التالي
    </button>
  </nav>
</template>
