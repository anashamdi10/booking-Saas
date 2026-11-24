<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'

import { ApiError, http } from '@/api/client'
import type { Booking, BookingStatus } from '@/api/types'
import { formatDateTime, formatPrice } from '@/composables/useFormat'

import AlertMessage from '@/components/ui/AlertMessage.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const bookings = ref<Booking[]>([])
const counts = ref<Record<string, number>>({})
const statusFilter = ref<BookingStatus | ''>('')
const loading = ref(true)
const error = ref('')
const actionError = ref('')
const busyId = ref<number | null>(null)

const filters: { value: BookingStatus | ''; label: string }[] = [
  { value: '', label: 'الكل' },
  { value: 'pending', label: 'بانتظار التأكيد' },
  { value: 'confirmed', label: 'مؤكد' },
  { value: 'completed', label: 'مكتمل' },
  { value: 'cancelled', label: 'ملغي' },
  { value: 'rejected', label: 'مرفوض' },
]

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const { data } = await http.get<{ data: Booking[]; counts: Record<string, number> }>(
      '/photographer/bookings',
      { params: { status: statusFilter.value || undefined, per_page: 50 } },
    )

    bookings.value = data.data
    counts.value = data.counts
  } catch {
    error.value = 'تعذر تحميل الحجوزات.'
  } finally {
    loading.value = false
  }
}

/**
 * Every change of the status filter reloads the list from the server, so the
 * table always reflects what the API returns for the selected status.
 */
watch(statusFilter, () => {
  void load()
})

async function changeStatus(booking: Booking, status: BookingStatus): Promise<void> {
  busyId.value = booking.id
  actionError.value = ''

  try {
    await http.patch(`/photographer/bookings/${booking.id}`, { status })
    await load()
  } catch (exception) {
    actionError.value =
      exception instanceof ApiError ? exception.message : 'تعذر تحديث حالة الحجز.'
  } finally {
    busyId.value = null
  }
}

function statusClass(status: BookingStatus): string {
  switch (status) {
    case 'confirmed':
      return 'bg-emerald-100 text-emerald-700'
    case 'pending':
      return 'bg-amber-100 text-amber-700'
    case 'completed':
      return 'bg-sky-100 text-sky-700'
    default:
      return 'bg-slate-100 text-slate-600'
  }
}

onMounted(load)
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold text-slate-900">الحجوزات</h1>

    <div class="mt-6 flex flex-wrap gap-2">
      <button
        v-for="filter in filters"
        :key="filter.value"
        type="button"
        class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
        :class="
          statusFilter === filter.value
            ? 'bg-slate-900 text-white'
            : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
        "
        @click="statusFilter = filter.value"
      >
        {{ filter.label }}
        <span v-if="filter.value && counts[filter.value]" class="tabular-nums">
          ({{ counts[filter.value] }})
        </span>
      </button>
    </div>

    <AlertMessage v-if="error" type="error" :message="error" class="mt-6" />
    <AlertMessage v-if="actionError" type="error" :message="actionError" class="mt-4" />

    <div v-if="loading" class="surface-card mt-6">
      <LoadingSpinner />
    </div>

    <EmptyState
      v-else-if="!bookings.length"
      class="mt-6"
      title="لا توجد حجوزات"
      description="ستظهر هنا طلبات الحجز التي يرسلها العملاء من صفحتك."
    />

    <ul v-else class="mt-6 space-y-4">
      <li v-for="booking in bookings" :key="booking.id" class="surface-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="chip" :class="statusClass(booking.status)">
                {{ booking.status_label }}
              </span>
              <span class="font-mono text-xs text-slate-400 tabular-nums">
                {{ booking.reference }}
              </span>
            </div>

            <p class="mt-2 font-bold text-slate-900">{{ booking.customer_name }}</p>
            <p class="text-sm text-slate-500">
              {{ booking.customer_email }}
              <template v-if="booking.customer_phone">
                · {{ booking.customer_phone }}
              </template>
            </p>

            <p class="mt-2 text-sm font-semibold text-slate-700 tabular-nums">
              {{ formatDateTime(booking.start_at) }}
              <template v-if="booking.package"> — {{ booking.package.name }}</template>
            </p>

            <p v-if="booking.notes" class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
              {{ booking.notes }}
            </p>
          </div>

          <div class="flex flex-col items-end gap-2">
            <p
              v-if="booking.total_price"
              class="font-extrabold text-slate-900 tabular-nums"
            >
              {{ formatPrice(booking.total_price, booking.currency) }}
            </p>

            <div class="flex gap-2">
              <button
                v-if="booking.status === 'pending'"
                type="button"
                class="btn bg-emerald-600 text-white hover:brightness-95"
                :disabled="busyId === booking.id"
                @click="changeStatus(booking, 'confirmed')"
              >
                تأكيد
              </button>
              <button
                v-if="booking.status === 'pending' || booking.status === 'confirmed'"
                type="button"
                class="btn btn-ghost"
                :disabled="busyId === booking.id"
                @click="changeStatus(booking, 'cancelled')"
              >
                إلغاء
              </button>
              <button
                v-if="booking.status === 'confirmed'"
                type="button"
                class="btn btn-dark"
                :disabled="busyId === booking.id"
                @click="changeStatus(booking, 'completed')"
              >
                إكمال
              </button>
            </div>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>
