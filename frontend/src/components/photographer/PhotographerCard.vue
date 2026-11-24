<script setup lang="ts">
import { RouterLink } from 'vue-router'

import type { Photographer } from '@/api/types'
import { formatNumber } from '@/composables/useFormat'

defineProps<{ photographer: Photographer }>()
</script>

<template>
  <article
    class="surface-card group overflow-hidden transition hover:-translate-y-1 hover:shadow-lg"
  >
    <div class="relative h-40 overflow-hidden bg-slate-100">
      <img
        v-if="photographer.cover_url"
        :src="photographer.cover_url"
        :alt="`غلاف ${photographer.name}`"
        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
        loading="lazy"
      />
      <div
        v-else
        class="grid h-full w-full place-items-center text-4xl font-extrabold text-white"
        :style="{ backgroundColor: photographer.theme.primary }"
        aria-hidden="true"
      >
        {{ photographer.name.charAt(0) }}
      </div>

      <span
        v-if="photographer.is_featured"
        class="absolute top-3 end-3 rounded-full px-3 py-1 text-xs font-bold text-white shadow"
        :style="{ backgroundColor: photographer.theme.accent }"
      >
        مصور مميز
      </span>
    </div>

    <div class="p-5">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <h3 class="truncate text-lg font-extrabold text-slate-900">{{ photographer.name }}</h3>
          <p class="mt-0.5 truncate text-sm text-slate-500">
            {{ photographer.headline ?? 'مصور محترف' }}
          </p>
        </div>

        <div class="shrink-0 text-center">
          <p class="flex items-center gap-1 text-sm font-bold text-amber-500 tabular-nums">
            <span aria-hidden="true">★</span>
            {{ formatNumber(photographer.rating_avg) }}
          </p>
          <p class="text-[11px] text-slate-400 tabular-nums">
            {{ formatNumber(photographer.rating_count) }} تقييم
          </p>
        </div>
      </div>

      <dl class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-500">
        <div v-if="photographer.city" class="flex items-center gap-1">
          <span aria-hidden="true">📍</span>
          {{ photographer.city }}
        </div>
        <div v-if="photographer.years_experience" class="flex items-center gap-1">
          <span aria-hidden="true">🎞</span>
          {{ photographer.years_experience }} سنة خبرة
        </div>
        <div class="flex items-center gap-1">
          <span aria-hidden="true">📦</span>
          {{ photographer.packages_count }} باقة
        </div>
      </dl>

      <RouterLink
        :to="{ name: 'photographer-profile', params: { slug: photographer.slug } }"
        class="btn btn-dark mt-5 w-full"
      >
        عرض الصفحة والحجز
      </RouterLink>
    </div>
  </article>
</template>
