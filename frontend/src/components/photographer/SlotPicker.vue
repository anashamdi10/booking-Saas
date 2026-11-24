<script setup lang="ts">
import type { AvailabilityDay } from '@/api/types'
import { formatDate } from '@/composables/useFormat'

defineProps<{
  days: AvailabilityDay[]
  selectedStart: string | null
  loading?: boolean
}>()

const emit = defineEmits<{ select: [start: string] }>()

function isSelected(start: string, selected: string | null): boolean {
  return selected !== null && new Date(start).getTime() === new Date(selected).getTime()
}
</script>

<template>
  <div>
    <p v-if="loading" class="text-sm text-slate-500">جارٍ تحميل المواعيد المتاحة...</p>

    <p
      v-else-if="days.length === 0"
      class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500"
    >
      لا توجد مواعيد متاحة حالياً، يمكنك التواصل عبر واتساب أو اختيار موعداً آخر.
    </p>

    <div v-else class="space-y-4">
      <div v-for="day in days" :key="day.date">
        <p class="mb-2 text-sm font-bold text-slate-700">{{ formatDate(day.date) }}</p>

        <div class="flex flex-wrap gap-2">
          <button
            v-for="slot in day.slots"
            :key="slot.start"
            type="button"
            class="rounded-lg border px-3 py-2 text-sm font-semibold tabular-nums transition"
            :class="
              isSelected(slot.start, selectedStart)
                ? 'text-white'
                : 'border-slate-300 bg-white text-slate-700 hover:border-slate-900'
            "
            :style="
              isSelected(slot.start, selectedStart)
                ? { backgroundColor: 'var(--brand-accent)', borderColor: 'var(--brand-accent)' }
                : undefined
            "
            @click="emit('select', slot.start)"
          >
            {{ slot.label }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
