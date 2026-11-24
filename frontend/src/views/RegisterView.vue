<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

import AlertMessage from '@/components/ui/AlertMessage.vue'
import BaseInput from '@/components/ui/BaseInput.vue'

const auth = useAuthStore()
const router = useRouter()

const form = ref({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
})
const fieldErrors = ref<Record<string, string[]>>({})

async function submit(): Promise<void> {
  fieldErrors.value = {}

  const success = await auth.register({
    name: form.value.name,
    email: form.value.email,
    phone: form.value.phone || undefined,
    password: form.value.password,
    password_confirmation: form.value.password_confirmation,
  })

  if (success) {
    await router.push({ name: 'photographer-dashboard' })
  }
}
</script>

<template>
  <div class="container-page grid min-h-[70vh] place-items-center py-16">
    <div class="surface-card w-full max-w-lg p-8">
      <h1 class="text-2xl font-extrabold text-slate-900">إنشاء حساب مصور</h1>
      <p class="mt-2 text-sm text-slate-500">
        سيُراجع فريق الإدارة طلبك وتُفعّل الباقة قبل ظهور صفحتك للعميل.
      </p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <AlertMessage v-if="auth.error" type="error" :message="auth.error" />

        <BaseInput
          v-model="form.name"
          label="الاسم"
          required
          autocomplete="name"
          :error="fieldErrors.name?.[0] ?? ''"
        />

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
          v-model="form.phone"
          label="رقم الجوال (اختياري)"
          type="tel"
          autocomplete="tel"
          placeholder="05xxxxxxxx"
          :error="fieldErrors.phone?.[0] ?? ''"
        />

        <BaseInput
          v-model="form.password"
          label="كلمة المرور"
          type="password"
          required
          autocomplete="new-password"
          hint="10 أحرف على الأقل مع أرقام ورموز وحرف كبير وصغير"
          :error="fieldErrors.password?.[0] ?? ''"
        />

        <BaseInput
          v-model="form.password_confirmation"
          label="تأكيد كلمة المرور"
          type="password"
          required
          autocomplete="new-password"
          :error="fieldErrors.password_confirmation?.[0] ?? ''"
        />

        <button type="submit" class="btn btn-primary w-full" :disabled="auth.loading">
          {{ auth.loading ? 'جارٍ الإنشاء...' : 'إنشاء الحساب' }}
        </button>
      </form>

      <p class="mt-6 text-center text-sm text-slate-600">
        لديك حساب بالفعل؟
        <RouterLink to="/login" class="font-bold text-brand-600 hover:underline">تسجيل الدخول</RouterLink>
      </p>
    </div>
  </div>
</template>
