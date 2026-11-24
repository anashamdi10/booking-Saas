<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import {
  notificationPermission,
  notify,
  requestPermission,
  type NotificationPermissionState,
} from '@/composables/useBrowserNotification'

const route = useRoute()

const reference = computed(() => (route.query.ref as string) ?? '')
const photographer = computed(() => (route.query.photographer as string) ?? '')

/**
 * 'default' means the browser still has to ask. Permission can only be
 * requested from a click, so the card below offers the button instead of
 * prompting on its own — prompting here would be silently refused.
 */
const permission = ref<NotificationPermissionState>(notificationPermission())
const notified = ref(false)

function notificationBody(): string {
  const parts: string[] = []

  if (photographer.value) parts.push(`حجزك مع ${photographer.value}`)
  if (reference.value) parts.push(`رقم الحجز ${reference.value}`)

  return parts.length > 0 ? `${parts.join(' — ')}.` : 'سيتواصل معك المصور لتأكيد الموعد.'
}

function fire(): void {
  if (notified.value) return

  notified.value = notify('تم استلام طلب الحجز', notificationBody())
}

onMounted(() => {
  // Already allowed on an earlier visit: show the confirmation right away.
  if (permission.value === 'granted') fire()
})

async function enable(): Promise<void> {
  permission.value = await requestPermission()

  if (permission.value === 'granted') fire()
}
</script>

<template>
  <div class="container-page grid min-h-[60vh] place-items-center py-16">
    <div class="surface-card w-full max-w-lg p-8 text-center">
      <span
        class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-emerald-100 text-3xl text-emerald-600"
        aria-hidden="true"
      >
        ✓
      </span>

      <h1 class="mt-6 text-2xl font-extrabold text-slate-900">تم استلام طلب الحجز</h1>

      <p class="mt-3 leading-relaxed text-slate-600">
        سيتواصل معك المصور لتأكيد الموعد. يمكنك أيضاً مراسلة المصور مباشرة عبر واتساب.
      </p>

      <div v-if="reference" class="mt-6 rounded-xl bg-slate-50 px-4 py-3">
        <p class="text-xs font-semibold text-slate-500">رقم الحجز</p>
        <p class="mt-1 font-mono text-lg font-extrabold text-slate-900 tabular-nums">
          {{ reference }}
        </p>
      </div>

      <!-- Browser notification: offered on the confirmation screen because the
           API refuses to prompt outside a click. -->
      <div
        v-if="permission !== 'unsupported' && permission !== 'denied'"
        class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-right"
      >
        <p class="text-sm font-extrabold text-slate-800">إشعارات المتصفح</p>

        <p class="mt-1 text-xs leading-relaxed text-slate-500">
          فعّلها ليظهر لك تنبيه فوري في متصفحك برقم حجزك عند إتمام الحجز.
        </p>

        <button v-if="permission === 'default'" type="button" class="btn btn-dark mt-3" @click="enable">
          تفعيل الإشعارات
        </button>

        <p v-else-if="notified" class="mt-3 text-xs font-bold text-emerald-600">
          ✓ تم إرسال التنبيه إلى متصفحك.
        </p>
      </div>

      <p v-else class="mt-6 text-xs leading-relaxed text-slate-400">
        إشعارات المتصفح معطّلة لهذا الموقع. يمكنك تفعيلها من إعدادات الموقع في متصفحك.
      </p>

      <div class="mt-8 flex flex-col gap-3 sm:flex-row-reverse">
        <RouterLink to="/photographers" class="btn btn-primary">تصفّح مصورين آخرين</RouterLink>
        <RouterLink to="/" class="btn btn-ghost">العودة للرئيسية</RouterLink>
      </div>
    </div>
  </div>
</template>
