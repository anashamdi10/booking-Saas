<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

import AlertMessage from '@/components/ui/AlertMessage.vue'
import BaseInput from '@/components/ui/BaseInput.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = ref({ email: '', password: '' })
const fieldErrors = ref<Record<string, string[]>>({})

async function submit(): Promise<void> {
  fieldErrors.value = {}

  const success = await auth.login(form.value.email, form.value.password)

  if (!success) {
    return
  }

  const redirect = (route.query.redirect as string) ?? null

  await router.push(redirect ?? (auth.isAdmin ? { name: 'admin-dashboard' } : { name: 'photographer-dashboard' }))
}
</script>

<template>
  <div class="container-page grid min-h-[70vh] place-items-center py-16">
    <div class="surface-card w-full max-w-md p-8">
      <h1 class="text-2xl font-extrabold text-slate-900">تسجيل الدخول</h1>
      <p class="mt-2 text-sm text-slate-500">ادخل إلى لوحة تحكم المصور أو الإدارة.</p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <AlertMessage v-if="auth.error" type="error" :message="auth.error" />

        <BaseInput
          v-model="form.email"
          label="البريد الإلكتروني"
          type="email"
          required
          autocomplete="email"
          placeholder="name@example.com"
          :error="fieldErrors.email?.[0] ?? ''"
        />

        <BaseInput
          v-model="form.password"
          label="كلمة المرور"
          type="password"
          required
          autocomplete="current-password"
          :error="fieldErrors.password?.[0] ?? ''"
        />

        <button type="submit" class="btn btn-primary w-full" :disabled="auth.loading">
          {{ auth.loading ? 'جارٍ الدخول...' : 'دخول' }}
        </button>
      </form>

      <p class="mt-6 text-center text-sm text-slate-600">
        ليس لديك حساب؟
        <RouterLink to="/register" class="font-bold text-brand-600 hover:underline">
          سجّل كـ مصور
        </RouterLink>
      </p>
    </div>
  </div>
</template>
