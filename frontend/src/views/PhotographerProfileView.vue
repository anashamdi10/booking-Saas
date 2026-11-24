<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute } from 'vue-router'

import { ApiError, http } from '@/api/client'
import type { PhotographerDetail } from '@/api/types'
import { formatNumber, weekdayLabel } from '@/composables/useFormat'

import BookingModal from '@/components/photographer/BookingModal.vue'
import PackageCard from '@/components/photographer/PackageCard.vue'
import WhatsAppButton from '@/components/photographer/WhatsAppButton.vue'
import AlertMessage from '@/components/ui/AlertMessage.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const route = useRoute()

const detail = ref<PhotographerDetail | null>(null)
const loading = ref(true)
const error = ref('')
const bookingOpen = ref(false)
const bookingReference = ref('')

const theme = computed(() => detail.value?.profile.theme ?? {
  primary: '#111827',
  accent: '#f97316',
  background: '#ffffff',
  surface: '#f8fafc',
  text: '#0f172a',
})

const pageStyle = computed(() => ({
  '--brand-primary': theme.value.primary,
  '--brand-accent': theme.value.accent,
  '--brand-surface': theme.value.surface,
  '--brand-background': theme.value.background,
  '--brand-text': theme.value.text,
}))

let previewChannel: BroadcastChannel | null = null

/** Reloads the public page. Keeps the current view visible while refreshing. */
async function loadProfile(): Promise<void> {
  try {
    const { data } = await http.get<{ data: PhotographerDetail }>(
      `/photographers/${String(route.params.slug)}`,
    )

    detail.value = data.data
    error.value = ''
  } catch (exception) {
    error.value =
      exception instanceof ApiError ? exception.message : 'تعذر تحميل صفحة المصور.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadProfile()

  if (typeof BroadcastChannel === 'undefined') {
    return
  }

  // The dashboard broadcasts a save so this preview tab can show the latest
  // state without a manual reload.
  previewChannel = new BroadcastChannel('photographer-preview')
  previewChannel.addEventListener('message', (event: MessageEvent<{ slug?: string }>) => {
    if (event.data?.slug === route.params.slug) void loadProfile()
  })
})

onUnmounted(() => {
  previewChannel?.close()
})

function onBooked(reference: string): void {
  bookingReference.value = reference
  bookingOpen.value = false

  const params = new URLSearchParams({ ref: reference })

  // Carried over so the confirmation screen can name the photographer in the
  // browser notification it offers.
  if (detail.value?.profile.name) params.set('photographer', detail.value.profile.name)

  void location.assign(`/booking/success?${params.toString()}`)
}
</script>

<template>
  <div :style="pageStyle">
    <div v-if="loading" class="container-page py-16">
      <LoadingSpinner label="جارٍ تحميل صفحة المصور..." />
    </div>

    <AlertMessage v-else-if="error" type="error" :message="error" class="container-page my-10" />

    <template v-else-if="detail">
      <section class="relative overflow-hidden" :style="{ backgroundColor: theme.primary }">
        <img
          v-if="detail.profile.cover_url"
          :src="detail.profile.cover_url"
          :alt="`غلاف ${detail.profile.name}`"
          class="absolute inset-0 h-full w-full object-cover opacity-30"
        />

        <div class="container-page relative py-16">
          <div class="flex flex-col items-start gap-6 sm:flex-row sm:items-center">
            <div
              class="grid h-24 w-24 shrink-0 place-items-center overflow-hidden rounded-3xl bg-white/10 text-4xl font-extrabold text-white ring-1 ring-white/20"
            >
              <img
                v-if="detail.profile.logo_url"
                :src="detail.profile.logo_url"
                :alt="`شعار ${detail.profile.name}`"
                class="h-full w-full object-cover"
              />
              <span v-else>{{ detail.profile.name.charAt(0) }}</span>
            </div>

            <div class="min-w-0 flex-1">
              <h1 class="text-3xl font-extrabold text-white sm:text-4xl">
                {{ detail.profile.name }}
              </h1>
              <p class="mt-1 text-lg text-white/80">
                {{ detail.profile.headline ?? 'مصور محترف' }}
              </p>

              <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-white/80">
                <span v-if="detail.profile.city" class="flex items-center gap-1">
                  <span aria-hidden="true">📍</span> {{ detail.profile.city }}
                </span>
                <span class="flex items-center gap-1">
                  <span aria-hidden="true">⭐</span>
                  {{ formatNumber(detail.profile.rating_avg) }}
                  ({{ formatNumber(detail.profile.rating_count) }} تقييم)
                </span>
                <span v-if="detail.profile.years_experience" class="flex items-center gap-1">
                  <span aria-hidden="true">🎞</span>
                  {{ detail.profile.years_experience }} سنة خبرة
                </span>
                <span class="flex items-center gap-1">
                  <span aria-hidden="true">✔</span>
                  {{ formatNumber(detail.bookings_count) }} جلسة مكتملة
                </span>
              </div>
            </div>

            <div class="flex w-full flex-col gap-3 sm:w-auto">
              <button type="button" class="btn btn-primary" @click="bookingOpen = true">
                احجز موعداً
              </button>
              <WhatsAppButton
                :url="detail.whatsapp_url"
                variant="outline"
                label="تواصل عبر واتساب"
              />
            </div>
          </div>
        </div>
      </section>

      <section v-if="detail.portfolio.length" class="container-page py-12">
        <h2 class="mb-6 text-2xl font-extrabold text-slate-900">معرض الأعمال</h2>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
          <figure
            v-for="image in detail.portfolio"
            :key="image.id"
            class="aspect-square overflow-hidden rounded-2xl bg-slate-100"
          >
            <img
              :src="image.thumbnail_url ?? image.url"
              :alt="`صورة من أعمال ${detail.profile.name}`"
              class="h-full w-full object-cover transition duration-300 hover:scale-105"
              loading="lazy"
            />
          </figure>
        </div>
      </section>

      <section v-if="detail.packages.length" class="border-y border-slate-200 bg-white py-12">
        <div class="container-page">
          <h2 class="mb-6 text-2xl font-extrabold text-slate-900">الباقات والأسعار</h2>

          <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <PackageCard
              v-for="(item, index) in detail.packages"
              :key="item.id"
              :package="item"
              :featured="index === 0"
            />
          </div>
        </div>
      </section>

      <section class="container-page grid gap-10 py-12 lg:grid-cols-3">
        <div class="lg:col-span-2">
          <h2 class="mb-4 text-2xl font-extrabold text-slate-900">عن المصور</h2>
          <p class="whitespace-pre-line leading-loose text-slate-700">
            {{ detail.profile.bio ?? 'لم يضف المصور نبذة بعد.' }}
          </p>
        </div>

        <aside class="surface-card h-fit p-6">
          <h2 class="mb-4 font-extrabold text-slate-900">ساعات العمل</h2>

          <ul v-if="detail.working_hours.length" class="space-y-2 text-sm">
            <li
              v-for="hour in detail.working_hours"
              :key="hour.weekday"
              class="flex items-center justify-between border-b border-slate-100 pb-2 last:border-0"
            >
              <span class="font-semibold text-slate-700">{{ weekdayLabel(hour.weekday) }}</span>
              <span class="tabular-nums text-slate-500">
                {{ hour.start_time }} — {{ hour.end_time }}
              </span>
            </li>
          </ul>

          <p v-else class="text-sm text-slate-500">لم يحدد المصور ساعات العمل بعد.</p>

          <button type="button" class="btn btn-primary mt-6 w-full" @click="bookingOpen = true">
            اختر موعداً متاحاً
          </button>

          <WhatsAppButton
            :url="detail.whatsapp_url"
            class="mt-3 w-full"
            label="اسأل عبر واتساب"
          />
        </aside>
      </section>

      <BookingModal
        :open="bookingOpen"
        :photographer-slug="detail.profile.slug"
        :photographer-name="detail.profile.name"
        :packages="detail.packages"
        @close="bookingOpen = false"
        @booked="onBooked"
      />

      <p v-if="bookingReference" class="sr-only">{{ bookingReference }}</p>
    </template>
  </div>
</template>
