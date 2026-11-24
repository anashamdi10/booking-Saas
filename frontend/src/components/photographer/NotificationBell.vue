<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import { ApiError, http } from '@/api/client'
import type { AppNotification, NotificationListResponse } from '@/api/types'
import { formatDate, formatDateTime, formatNumber } from '@/composables/useFormat'

const router = useRouter()

const items = ref<AppNotification[]>([])
const unread = ref(0)
const open = ref(false)
const error = ref('')

const POLL_INTERVAL_MS = 30_000
let pollTimer: number | undefined

/**
 * Fetches the latest notifications plus the unread count in one call. Fails
 * silently on purpose: a network hiccup must not disturb the dashboard.
 */
async function load(): Promise<void> {
  try {
    const { data } = await http.get<NotificationListResponse>('/photographer/notifications')

    items.value = data.data
    unread.value = data.meta.unread
    error.value = ''
  } catch (exception) {
    error.value = exception instanceof ApiError ? exception.message : ''
  }
}

/** One readable line: who booked and when. */
function summary(item: AppNotification): string {
  const parts: string[] = []

  if (item.customer_name) parts.push(item.customer_name)
  if (item.start_at) parts.push(formatDateTime(item.start_at))

  return parts.join(' — ')
}

function timeAgo(iso: string | null): string {
  if (!iso) return ''

  const minutes = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60_000))

  if (minutes < 1) return 'الآن'
  if (minutes < 60) return `منذ ${formatNumber(minutes)} دقيقة`

  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `منذ ${formatNumber(hours)} ساعة`

  const days = Math.floor(hours / 24)
  if (days < 30) return `منذ ${formatNumber(days)} يوم`

  return formatDate(iso)
}

async function openItem(item: AppNotification): Promise<void> {
  open.value = false

  if (item.read_at === null) {
    try {
      await http.patch(`/photographer/notifications/${item.id}/read`)

      item.read_at = new Date().toISOString()
      unread.value = Math.max(0, unread.value - 1)
    } catch {
      // Marking as read is a convenience; navigation still proceeds.
    }
  }

  if (item.url) void router.push(item.url)
}

async function readAll(): Promise<void> {
  try {
    await http.patch('/photographer/notifications/read-all')

    const now = new Date().toISOString()
    items.value = items.value.map((item) => ({ ...item, read_at: item.read_at ?? now }))
    unread.value = 0
  } catch (exception) {
    error.value = exception instanceof ApiError ? exception.message : 'تعذر تحديث الإشعارات.'
  }
}

onMounted(() => {
  void load()
  pollTimer = window.setInterval(() => void load(), POLL_INTERVAL_MS)
})

onBeforeUnmount(() => {
  if (pollTimer !== undefined) window.clearInterval(pollTimer)
})
</script>

<template>
  <div class="relative">
    <button
      type="button"
      class="relative grid h-9 w-9 place-items-center rounded-lg border border-slate-200 bg-white text-base text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
      aria-label="الإشعارات"
      :aria-expanded="open"
      aria-haspopup="true"
      @click="open = !open"
    >
      <span aria-hidden="true">🔔</span>

      <span
        v-if="unread > 0"
        class="absolute -top-1.5 -left-1.5 grid h-5 min-w-5 place-items-center rounded-full bg-rose-500 px-1 text-[11px] font-bold text-white tabular-nums"
      >
        {{ unread > 9 ? '9+' : formatNumber(unread) }}
      </span>
    </button>

    <template v-if="open">
      <!-- Click away closes the panel without adding a document listener. -->
      <div class="fixed inset-0 z-20" @click="open = false" />

      <div
        class="absolute top-full right-0 z-30 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
        role="menu"
      >
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
          <p class="text-sm font-extrabold text-slate-900">الإشعارات</p>

          <button
            v-if="unread > 0"
            type="button"
            class="text-xs font-semibold text-brand-600 hover:underline"
            @click="readAll"
          >
            تعليم الكل كمقروء
          </button>
        </div>

        <p v-if="error" class="px-4 py-3 text-xs text-rose-600">{{ error }}</p>

        <ul v-else-if="items.length" class="max-h-80 overflow-y-auto">
          <li v-for="item in items" :key="item.id">
            <button
              type="button"
              class="flex w-full gap-3 px-4 py-3 text-right transition hover:bg-slate-50"
              role="menuitem"
              @click="openItem(item)"
            >
              <span
                class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                :class="item.read_at ? 'bg-slate-200' : 'bg-brand-600'"
                aria-hidden="true"
              />

              <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-slate-800">{{ item.title }}</span>

                <span v-if="summary(item)" class="mt-0.5 block truncate text-xs text-slate-500">
                  {{ summary(item) }}
                </span>

                <span class="mt-1 block text-[11px] text-slate-400">{{ timeAgo(item.created_at) }}</span>
              </span>
            </button>
          </li>
        </ul>

        <p v-else class="px-4 py-6 text-center text-sm text-slate-500">لا توجد إشعارات بعد.</p>

        <div class="border-t border-slate-100 bg-slate-50 px-4 py-2 text-center">
          <RouterLink
            to="/photographer/bookings"
            class="text-xs font-semibold text-slate-600 hover:text-slate-900"
            @click="open = false"
          >
            عرض كل الحجوزات
          </RouterLink>
        </div>
      </div>
    </template>
  </div>
</template>
