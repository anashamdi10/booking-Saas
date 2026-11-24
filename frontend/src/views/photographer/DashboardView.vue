<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { http } from '@/api/client'
import { formatNumber } from '@/composables/useFormat'

import AlertMessage from '@/components/ui/AlertMessage.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

interface DashboardStats {
  bookings_total: number
  bookings_pending: number
  bookings_confirmed: number
  bookings_completed: number
  visits_total: number
  visits_today: number
  revenue_paid: number
  revenue_outstanding: number
}

const stats = ref<DashboardStats | null>(null)
const series = ref<{ date: string; visits: number; bookings: number }[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const { data } = await http.get<{ data: { stats: DashboardStats; series: typeof series.value } }>(
      '/photographer/dashboard',
    )

    stats.value = data.data.stats
    series.value = data.data.series
  } catch {
    error.value = 'تعذر تحميل لوحة التحكم.'
  } finally {
    loading.value = false
  }
})

const maxVisits = () => Math.max(1, ...series.value.map((point) => point.visits))
const maxBookings = () => Math.max(1, ...series.value.map((point) => point.bookings))

/** A day with no activity renders no bar instead of a misleading stub. */
const isEmpty = () => series.value.every((point) => point.visits === 0 && point.bookings === 0)

/** Scales a value against the busiest day, keeping a readable minimum height. */
function barHeight(value: number, max: number): number {
  return value > 0 ? Math.max(6, (value / max) * 100) : 0
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold text-slate-900">نظرة عامة</h1>

    <div v-if="loading" class="surface-card mt-6">
      <LoadingSpinner />
    </div>

    <AlertMessage v-else-if="error" type="error" :message="error" class="mt-6" />

    <template v-else>
      <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="surface-card p-5">
          <dt class="text-xs font-semibold text-slate-500">حجوزات بانتظار التأكيد</dt>
          <dd class="mt-2 text-3xl font-extrabold text-amber-600 tabular-nums">
            {{ formatNumber(stats?.bookings_pending ?? 0) }}
          </dd>
        </div>
        <div class="surface-card p-5">
          <dt class="text-xs font-semibold text-slate-500">حجوزات مؤكدة</dt>
          <dd class="mt-2 text-3xl font-extrabold text-emerald-600 tabular-nums">
            {{ formatNumber(stats?.bookings_confirmed ?? 0) }}
          </dd>
        </div>
        <div class="surface-card p-5">
          <dt class="text-xs font-semibold text-slate-500">زوار الصفحة (اليوم)</dt>
          <dd class="mt-2 text-3xl font-extrabold text-slate-900 tabular-nums">
            {{ formatNumber(stats?.visits_today ?? 0) }}
          </dd>
        </div>
        <div class="surface-card p-5">
          <dt class="text-xs font-semibold text-slate-500">إجمالي الزيارات</dt>
          <dd class="mt-2 text-3xl font-extrabold text-slate-900 tabular-nums">
            {{ formatNumber(stats?.visits_total ?? 0) }}
          </dd>
        </div>
      </dl>

      <div class="surface-card mt-6 p-6">
        <h2 class="font-extrabold text-slate-900">آخر 30 يوماً</h2>

        <p
          v-if="isEmpty()"
          class="mt-6 flex h-40 items-center justify-center rounded-lg border border-dashed border-slate-200 text-sm text-slate-400"
        >
          لا توجد زيارات أو حجوزات مسجّلة في هذه المدة
        </p>

        <div v-else class="mt-6 flex h-40 items-end gap-1">
          <div
            v-for="point in series"
            :key="point.date"
            class="flex h-full flex-1 items-end justify-center gap-px"
          >
            <div
              class="group relative w-1/2 rounded-t bg-slate-300 transition hover:bg-slate-400"
              :style="{ height: `${barHeight(point.visits, maxVisits())}%` }"
            >
              <span
                class="pointer-events-none absolute -top-8 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-[10px] text-white group-hover:block"
              >
                زيارات {{ formatNumber(point.visits) }}
              </span>
            </div>

            <div
              class="group relative w-1/2 rounded-t bg-brand-500 transition hover:bg-brand-600"
              :style="{ height: `${barHeight(point.bookings, maxBookings())}%` }"
            >
              <span
                class="pointer-events-none absolute -top-8 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-[10px] text-white group-hover:block"
              >
                حجوزات {{ formatNumber(point.bookings) }}
              </span>
            </div>
          </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-500">
          <span class="flex items-center gap-1.5">
            <i class="inline-block h-2.5 w-2.5 rounded-sm bg-slate-300"></i>
            الزيارات
          </span>
          <span class="flex items-center gap-1.5">
            <i class="inline-block h-2.5 w-2.5 rounded-sm bg-brand-500"></i>
            الحجوزات
          </span>
          <span class="ms-auto">
            إجمالي الحجوزات: {{ formatNumber(stats?.bookings_total ?? 0) }} · المكتملة:
            {{ formatNumber(stats?.bookings_completed ?? 0) }}
          </span>
        </div>
      </div>
    </template>
  </div>
</template>
