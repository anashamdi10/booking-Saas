<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const links = [
  { name: 'admin-dashboard', label: 'نظرة عامة' },
  { name: 'admin-photographers', label: 'المصورون' },
  { name: 'admin-packages', label: 'باقات التصوير' },
  { name: 'admin-plans', label: 'باقات الاشتراك' },
]
</script>

<template>
  <div class="container-page py-10">
    <div class="grid gap-8 lg:grid-cols-[240px_1fr]">
      <aside>
        <div class="surface-card p-4">
          <p class="px-2 pb-3 text-sm font-extrabold text-slate-900">{{ auth.user?.name }}</p>
          <p class="px-2 pb-4 text-xs text-slate-500">لوحة الإدارة</p>

          <nav class="space-y-1">
            <RouterLink
              v-for="link in links"
              :key="link.name"
              :to="{ name: link.name }"
              class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
              exact-active-class="!bg-slate-900 !text-white hover:bg-slate-900 hover:text-white"
            >
              {{ link.label }}
            </RouterLink>
          </nav>
        </div>
      </aside>

      <section class="min-w-0">
        <RouterView />
      </section>
    </div>
  </div>
</template>
