<script setup lang="ts">
import type { Package } from '@/api/types'
import { formatDuration, formatPrice } from '@/composables/useFormat'

defineProps<{ package: Package; featured?: boolean }>()
</script>

<template>
  <article
    class="surface-card relative flex h-full flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-lg"
    :class="{ 'ring-2': featured }"
    :style="featured ? { '--tw-ring-color': 'var(--brand-accent)' } : undefined"
  >
    <span
      v-if="featured"
      class="absolute -top-3 start-5 rounded-full px-3 py-1 text-xs font-bold text-white"
      style="background-color: var(--brand-accent)"
    >
      الأكثر طلباً
    </span>

    <h3 class="text-lg font-extrabold text-slate-900">{{ package.name }}</h3>

    <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-600">
      {{ package.description ?? 'خدمة تصوير متكاملة مع ألبوم رقمي وتسليم سريع.' }}
    </p>

    <ul v-if="package.deliverables?.length" class="mt-4 space-y-2 text-sm text-slate-600">
      <li v-for="item in package.deliverables" :key="item" class="flex items-center gap-2">
        <span class="text-emerald-500" aria-hidden="true">✓</span>
        {{ item }}
      </li>
    </ul>

    <div class="mt-5 flex items-end justify-between border-t border-slate-100 pt-4">
      <div>
        <p class="text-xl font-extrabold text-slate-900 tabular-nums">
          {{ formatPrice(package.price, package.currency) }}
        </p>
        <p class="text-xs text-slate-500">
          {{ formatDuration(package.duration_minutes) }}
          <template v-if="package.max_photos">
            · حتى {{ package.max_photos }} صورة
          </template>
        </p>
      </div>
    </div>
  </article>
</template>
