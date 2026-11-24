<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { ApiError, http } from '@/api/client'
import type { WorkingHour } from '@/api/types'
import { formatBytes, weekdayLabel } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'

import AlertMessage from '@/components/ui/AlertMessage.vue'

import AccountSecurityCard from '@/components/photographer/AccountSecurityCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const weekdays = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت']
const themeKeys = ['primary', 'accent', 'background', 'surface', 'text'] as const

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const auth = useAuthStore()

/** Slug of the public booking page, used to build the preview link. */
const slug = ref('')
const publicUrl = computed(() => (slug.value ? `/photographers/${slug.value}` : ''))

/** The public page only exists once an administrator approves the account. */
const isPubliclyVisible = computed(() => auth.user?.status === 'active' && publicUrl.value !== '')

/**
 * Named browsing context so repeated clicks reuse the same preview tab
 * instead of stacking up new ones.
 */
const PREVIEW_WINDOW = 'photographer-preview'

/** Signals an already open preview tab so it can reload the saved data. */
const previewChannel =
  typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel('photographer-preview')

function notifyPreview(): void {
  previewChannel?.postMessage({ slug: slug.value })
}

const profile = ref({
  name: '',
  headline: '',
  bio: '',
  city: '',
  phone: '',
  whatsapp: '',
  website: '',
  years_experience: '',
  theme: {
    primary: '#111827',
    accent: '#f97316',
    background: '#ffffff',
    surface: '#f8fafc',
    text: '#0f172a',
  },
})

const hours = ref<WorkingHour[]>([])
const storage = ref<{ limit: number; used: number; remaining: number } | null>(null)

/** Sign-in address, shown and reassigned by the account card. */
const accountEmail = ref('')

onMounted(async () => {
  try {
    const { data } = await http.get<{
      data: {
        profile: { slug: string; headline: string | null; bio: string | null; city: string | null; phone: string | null; whatsapp: string | null; website: string | null; years_experience: number | null; theme: Record<string, string> }
        user: { name: string; email: string }
        working_hours: WorkingHour[]
        storage: { limit: number; used: number; remaining: number }
      }
    }>('/photographer/profile')

    const payload = data.data

    profile.value = {
      name: payload.user.name,
      headline: payload.profile.headline ?? '',
      bio: payload.profile.bio ?? '',
      city: payload.profile.city ?? '',
      phone: payload.profile.phone ?? '',
      whatsapp: payload.profile.whatsapp ?? '',
      website: payload.profile.website ?? '',
      years_experience: payload.profile.years_experience?.toString() ?? '',
      theme: {
        primary: payload.profile.theme.primary ?? '#111827',
        accent: payload.profile.theme.accent ?? '#f97316',
        background: payload.profile.theme.background ?? '#ffffff',
        surface: payload.profile.theme.surface ?? '#f8fafc',
        text: payload.profile.theme.text ?? '#0f172a',
      },
    }

    hours.value = payload.working_hours
    storage.value = payload.storage
    slug.value = payload.profile.slug ?? ''
    accountEmail.value = payload.user.email
  } catch {
    error.value = 'تعذر تحميل بيانات الصفحة الشخصية.'
  } finally {
    loading.value = false
  }
})

async function saveProfile(): Promise<void> {
  saving.value = true
  error.value = ''
  success.value = ''
  fieldErrors.value = {}

  try {
    await http.put('/photographer/profile', {
      name: profile.value.name,
      headline: profile.value.headline || null,
      bio: profile.value.bio || null,
      city: profile.value.city || null,
      phone: profile.value.phone || null,
      whatsapp: profile.value.whatsapp || null,
      website: profile.value.website || null,
      years_experience: profile.value.years_experience ? Number(profile.value.years_experience) : null,
      theme: profile.value.theme,
    })

    success.value = 'تم حفظ بيانات الصفحة الشخصية.'

    // Tells an open preview tab to reload the freshly saved data.
    notifyPreview()
  } catch (exception) {
    if (exception instanceof ApiError) {
      error.value = exception.message
      fieldErrors.value = exception.errors
    } else {
      error.value = 'تعذر حفظ البيانات.'
    }
  } finally {
    saving.value = false
  }
}

async function saveHours(): Promise<void> {
  saving.value = true
  error.value = ''
  success.value = ''

  try {
    const { data } = await http.put<{ data: WorkingHour[] }>('/photographer/profile/working-hours', {
      hours: hours.value.map((hour) => ({
        weekday: hour.weekday,
        start_time: hour.start_time,
        end_time: hour.end_time,
        slot_interval_minutes: 60,
        is_active: true,
      })),
    })

    hours.value = data.data
    success.value = 'تم حفظ مواعيد العمل.'
  } catch (exception) {
    error.value = exception instanceof ApiError ? exception.message : 'تعذر حفظ مواعيد العمل.'
  } finally {
    saving.value = false
  }
}

function addHour(): void {
  hours.value.push({ weekday: 0, start_time: '16:00', end_time: '22:00' })
}

function removeHour(index: number): void {
  hours.value.splice(index, 1)
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900">الصفحة الشخصية</h1>
        <p class="mt-1 text-sm text-slate-500">
          هذه البيانات تظهر للعميل في صفحة المصور وتُستخدم في الحجز.
        </p>
      </div>

      <a
        v-if="isPubliclyVisible"
        :href="publicUrl"
        :target="PREVIEW_WINDOW"
        rel="noopener noreferrer"
        class="btn btn-dark inline-flex shrink-0 items-center gap-2"
      >
        معاينة صفحتك
        <span aria-hidden="true">↗</span>
      </a>

      <p v-else class="max-w-[16rem] text-xs leading-5 text-slate-400">
        صفحتك ستظهر للعميل بعد موافقة الإدارة على حسابك.
      </p>
    </div>

    <div v-if="loading" class="surface-card mt-6">
      <LoadingSpinner />
    </div>

    <template v-else>
      <AlertMessage v-if="error" type="error" :message="error" class="mt-6" />
      <AlertMessage v-if="success" type="success" :message="success" class="mt-6" />

      <div
        v-if="storage"
        class="surface-card mt-6 p-5"
      >
        <p class="text-sm font-bold text-slate-700">مساحة التخزين</p>
        <p class="mt-1 text-xs text-slate-500">
          المستخدم {{ formatBytes(storage.used) }} من {{ formatBytes(storage.limit) }} —
          المتبقي {{ formatBytes(storage.remaining) }}
        </p>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
          <div
            class="h-full rounded-full bg-brand-500"
            :style="{
              width: `${storage.limit > 0 ? Math.min(100, (storage.used / storage.limit) * 100) : 0}%`,
            }"
          />
        </div>
      </div>

      <form class="surface-card mt-6 space-y-4 p-6" @submit.prevent="saveProfile">
        <h2 class="font-extrabold text-slate-900">المعلومات الأساسية</h2>

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput v-model="profile.name" label="الاسم" required :error="fieldErrors.name?.[0] ?? ''" />
          <BaseInput
            v-model="profile.headline"
            label="العنوان المهني"
            placeholder="مثال: تصوير مناسبات وعائلات"
            :error="fieldErrors.headline?.[0] ?? ''"
          />
          <BaseInput v-model="profile.city" label="المدينة" :error="fieldErrors.city?.[0] ?? ''" />
          <BaseInput
            v-model="profile.years_experience"
            label="سنوات الخبرة"
            type="number"
            min="0"
            max="70"
            :error="fieldErrors.years_experience?.[0] ?? ''"
          />
          <BaseInput v-model="profile.phone" label="رقم الجوال" type="tel" :error="fieldErrors.phone?.[0] ?? ''" />
          <BaseInput
            v-model="profile.whatsapp"
            label="رقم واتساب"
            type="tel"
            placeholder="9665xxxxxxxx"
            :error="fieldErrors.whatsapp?.[0] ?? ''"
          />
          <div class="sm:col-span-2">
            <BaseInput
              v-model="profile.website"
              label="الموقع الإلكتروني"
              type="url"
              placeholder="https://example.com"
              :error="fieldErrors.website?.[0] ?? ''"
            />
          </div>
          <div class="sm:col-span-2">
            <BaseTextarea
              v-model="profile.bio"
              label="نبذة عنك"
              :rows="5"
              :maxlength="3000"
              :error="fieldErrors.bio?.[0] ?? ''"
            />
          </div>
        </div>

        <h2 class="pt-2 font-extrabold text-slate-900">ألوان الصفحة</h2>

        <div class="grid gap-4 sm:grid-cols-3">
          <label v-for="key in themeKeys" :key="key" class="block">
            <span class="field-label">{{ key }}</span>
            <div class="flex items-center gap-2">
              <input
                v-model="profile.theme[key]"
                type="color"
                class="h-10 w-12 cursor-pointer rounded-lg border border-slate-300 bg-white p-1"
              />
              <input
                v-model="profile.theme[key]"
                type="text"
                class="field-input font-mono text-xs"
                dir="ltr"
              />
            </div>
          </label>
        </div>

        <div class="flex justify-end border-t border-slate-100 pt-4">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'جارٍ الحفظ...' : 'حفظ التغييرات' }}
          </button>
        </div>
      </form>

      <section class="surface-card mt-6 p-6">
        <div class="flex items-center justify-between">
          <h2 class="font-extrabold text-slate-900">مواعيد العمل</h2>
          <button type="button" class="btn btn-ghost" @click="addHour">+ إضافة</button>
        </div>

        <p class="mt-1 text-sm text-slate-500">
          الأوقات المتاحة للحجز تُحسب تلقائياً من هذه المواعيد.
        </p>

        <div v-if="hours.length" class="mt-5 space-y-3">
          <div
            v-for="(hour, index) in hours"
            :key="index"
            class="grid items-end gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-4"
          >
            <label class="block">
              <span class="field-label">اليوم</span>
              <select v-model.number="hour.weekday" class="field-input">
                <option v-for="(day, dayIndex) in weekdays" :key="day" :value="dayIndex">
                  {{ day }}
                </option>
              </select>
            </label>

            <label class="block">
              <span class="field-label">من</span>
              <input v-model="hour.start_time" type="time" class="field-input" dir="ltr" />
            </label>

            <label class="block">
              <span class="field-label">إلى</span>
              <input v-model="hour.end_time" type="time" class="field-input" dir="ltr" />
            </label>

            <button type="button" class="btn btn-ghost" @click="removeHour(index)">حذف</button>
          </div>
        </div>

        <p v-else class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
          لم تضف أي مواعيد عمل بعد.
        </p>

        <div class="mt-5 flex justify-end">
          <button type="button" class="btn btn-primary" :disabled="saving" @click="saveHours">
            {{ saving ? 'جارٍ الحفظ...' : 'حفظ المواعيد' }}
          </button>
        </div>
      </section>

      <AccountSecurityCard :email="accountEmail" @changed="accountEmail = $event" />

      <p class="mt-4 text-xs text-slate-400">
        {{ weekdayLabel(0) }} = أول أيام الأسبوع. تُعرض المواعيد للعميل حسب توقيت المنصة.
      </p>
    </template>
  </div>
</template>
