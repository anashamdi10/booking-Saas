<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { computed } from 'vue'

import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const dashboardLink = computed(() =>
  auth.isAdmin ? { name: 'admin-dashboard' } : { name: 'photographer-dashboard' },
)

async function handleLogout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'home' })
}
</script>

<template>
  <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="container-page flex h-16 items-center justify-between gap-4">
      <RouterLink to="/" class="flex items-center gap-2 font-extrabold text-slate-900">
        <span
          class="grid h-9 w-9 place-items-center rounded-xl bg-slate-900 text-lg text-white"
          aria-hidden="true"
        >
          ◉
        </span>
        <span class="hidden sm:inline">حجوزات المصورين</span>
      </RouterLink>

      <nav class="flex items-center gap-1 text-sm font-semibold">
        <RouterLink
          to="/"
          class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
        >
          الرئيسية
        </RouterLink>
        <RouterLink
          to="/photographers"
          class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
        >
          المصورون
        </RouterLink>

        <template v-if="auth.isAuthenticated">
          <RouterLink
            :to="dashboardLink"
            class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
          >
            لوحة التحكم
          </RouterLink>
          <button
            type="button"
            class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
            @click="handleLogout"
          >
            خروج
          </button>
        </template>
        <template v-else>
          <RouterLink
            to="/login"
            class="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
          >
            دخول
          </RouterLink>
          <RouterLink to="/register" class="btn btn-primary ms-1">سجّل كـ مصور</RouterLink>
        </template>
      </nav>
    </div>
  </header>
</template>
