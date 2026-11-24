<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { http } from '@/api/client'
import type { SiteStats } from '@/api/types'
import { formatNumber, formatPrice } from '@/composables/useFormat'

import AlertMessage from '@/components/ui/AlertMessage.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const stats = ref<SiteStats | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const { data } = await http.get<{ data: SiteStats }>('/site/stats')
    stats.value = data.data
  } catch {
    error.value = 'تعذر تحميل الإحصائيات.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold text-slate-900">لوحة الإدارة</h1>
    <p class="mt-1 text-sm text-slate-500">نظرة عامة على المنصة.</p>

    <div v-if="loading" class="surface-card mt-6">
      <LoadingSpinner />
    </div>

    <AlertMessage v-else-if="error" type="error" :message="error" class="mt-6" />

    <dl v-else class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <div class="surface-card p-5">
        <dt class="text-xs font-semibold text-slate-500">المصورون النشطون</dt>
        <dd class="mt-2 text-3xl font-extrabold text-slate-900 tabular-nums">
          {{ formatNumber(stats?.photographers_count ?? 0) }}
        </dd>
      </div>
      <div class="surface-card p-5">
        <dt class="text-xs font-semibold text-slate-500">إجمالي الحجوزات</dt>
        <dd class="mt-2 text-3xl font-extrabold text-slate-900 tabular-nums">
          {{ formatNumber(stats?.bookings_count ?? 0) }}
        </dd>
      </div>
      <div class="surface-card p-5">
        <dt class="text-xs font-semibold text-slate-500">جلسات مكتملة</dt>
        <dd class="mt-2 text-3xl font-extrabold text-emerald-600 tabular-nums">
          {{ formatNumber(stats?.completed_bookings_count ?? 0) }}
        </dd>
      </div>
      <div class="surface-card p-5">
        <dt class="text-xs font-semibold text-slate-500">زوار اليوم</dt>
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
      <div class="surface-card p-5">
        <dt class="text-xs font-semibold text-slate-500">مدفوع من العملاء</dt>
        <dd class="mt-2 text-3xl font-extrabold text-slate-900 tabular-nums">
          {{ formatPrice(stats?.invoices_paid_total ?? 0) }}
        </dd>
      </div>
    </dl>
  </div>
</template>
