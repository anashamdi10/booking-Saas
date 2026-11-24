<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { http } from '@/api/client'
import type { Photographer, SiteStats } from '@/api/types'
import { formatNumber } from '@/composables/useFormat'

import PhotographerCard from '@/components/photographer/PhotographerCard.vue'
import AlertMessage from '@/components/ui/AlertMessage.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const stats = ref<SiteStats | null>(null)
const featured = ref<Photographer[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const [statsResponse, listResponse] = await Promise.all([
      http.get<{ data: SiteStats }>('/site/stats'),
      http.get<{ data: Photographer[] }>('/photographers', { params: { featured: true, per_page: 6 } }),
    ])

    stats.value = statsResponse.data.data
    featured.value = listResponse.data.data
  } catch {
    error.value = 'تعذر تحميل البيانات، يرجى تحديث الصفحة.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <section class="relative overflow-hidden bg-slate-900">
      <div
        class="absolute inset-0 opacity-25"
        style="background-image: radial-gradient(circle at 20% 20%, #f97316 0, transparent 45%)"
        aria-hidden="true"
      />

      <div class="container-page relative py-20 sm:py-28">
        <div class="max-w-3xl">
          <span
            class="chip bg-white/10 text-slate-200 ring-1 ring-white/20"
          >
            منصة حجوزات المصورين
          </span>

          <h1 class="mt-5 text-4xl font-extrabold leading-tight text-white sm:text-5xl">
            احجز موعد التصوير مع المصور المناسب
          </h1>

          <p class="mt-4 max-w-2xl text-base leading-relaxed text-slate-300 sm:text-lg">
            تصفّح المصورين، استعرض أعمالهم وأسعارهم، واختر الموعد المتاح — أو تواصل معهم مباشرة عبر
            واتساب.
          </p>

          <div class="mt-8 flex flex-wrap gap-3">
            <RouterLink to="/photographers" class="btn btn-primary">
              تصفّح المصورين
            </RouterLink>
            <RouterLink
              to="/register"
              class="btn bg-white/10 text-white ring-1 ring-white/25 hover:bg-white/20"
            >
              سجّل كـ مصور
            </RouterLink>
          </div>
        </div>
      </div>
    </section>

    <!--
      The cards overlap the hero, so this section needs its own stacking
      context (relative + z-10). Without it the positioned hero paints on top
      of these cards and hides their labels.
    -->
    <section class="container-page relative z-10 -mt-10">
      <div v-if="loading" class="surface-card">
        <LoadingSpinner />
      </div>

      <AlertMessage v-else-if="error" type="error" :message="error" class="mt-6" />

      <dl v-else class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="surface-card p-6">
          <dt class="text-sm font-semibold text-slate-500">عدد المصورين</dt>
          <dd class="mt-2 text-3xl font-extrabold text-slate-900 tabular-nums">
            {{ formatNumber(stats?.photographers_count ?? 0) }}
          </dd>
        </div>
        <div class="surface-card p-6">
          <dt class="text-sm font-semibold text-slate-500">المدن</dt>
          <dd class="mt-2 text-3xl font-extrabold text-slate-900 tabular-nums">
            {{ formatNumber(stats?.cities_count ?? 0) }}
          </dd>
        </div>
        <div class="surface-card p-6">
          <dt class="text-sm font-semibold text-slate-500">الحجوزات</dt>
          <dd class="mt-2 text-3xl font-extrabold text-slate-900 tabular-nums">
            {{ formatNumber(stats?.bookings_count ?? 0) }}
          </dd>
        </div>
        <div class="surface-card p-6">
          <dt class="text-sm font-semibold text-slate-500">جلسات مكتملة</dt>
          <dd class="mt-2 text-3xl font-extrabold text-slate-900 tabular-nums">
            {{ formatNumber(stats?.completed_bookings_count ?? 0) }}
          </dd>
        </div>
      </dl>
    </section>

    <section class="container-page py-16">
      <div class="mb-8 flex items-end justify-between gap-4">
        <div>
          <h2 class="text-2xl font-extrabold text-slate-900">مصورون مميزون</h2>
          <p class="mt-1 text-sm text-slate-500">أفضل المصورين حسب تقييمات العملاء.</p>
        </div>

        <RouterLink to="/photographers" class="text-sm font-bold text-brand-600 hover:underline">
          عرض الكل ←
        </RouterLink>
      </div>

      <div v-if="loading" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="index in 6" :key="index" class="surface-card h-80 animate-pulse bg-slate-100" />
      </div>

      <div v-else-if="featured.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <PhotographerCard
          v-for="photographer in featured"
          :key="photographer.id"
          :photographer="photographer"
        />
      </div>

      <div v-else class="surface-card px-6 py-14 text-center text-slate-500">
        لا يوجد مصورون متاحون حالياً.
      </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-16">
      <div class="container-page">
        <h2 class="text-center text-2xl font-extrabold text-slate-900">كيف تحجز موعدك؟</h2>

        <ol class="mt-10 grid gap-6 sm:grid-cols-3">
          <li class="surface-card p-6">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-100 font-extrabold text-brand-700">
              ١
            </span>
            <h3 class="mt-4 font-bold text-slate-900">ابحث عن مصور</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
              تصفّح Directory حسب المدينة ونوع الخدمة، وشاهد معرض أعمال كل مصور.
            </p>
          </li>
          <li class="surface-card p-6">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-100 font-extrabold text-brand-700">
              ٢
            </span>
            <h3 class="mt-4 font-bold text-slate-900">اختر الموعد</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
              اختر الباقة والموعد المتاح، وأرسل طلب الحجز خلال ثوانٍ.
            </p>
          </li>
          <li class="surface-card p-6">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-100 font-extrabold text-brand-700">
              ٣
            </span>
            <h3 class="mt-4 font-bold text-slate-900">أكّد الحجز</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
              يتواصل معك المصور لتأكيد الموعد، أو راسله مباشرة عبر واتساب.
            </p>
          </li>
        </ol>
      </div>
    </section>
  </div>
</template>
