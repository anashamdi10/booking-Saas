<script setup lang="ts">
import { ref } from 'vue'

import { ApiError, http } from '@/api/client'

import AlertMessage from '@/components/ui/AlertMessage.vue'
import BaseInput from '@/components/ui/BaseInput.vue'

const props = defineProps<{ email: string }>()
const emit = defineEmits<{ changed: [email: string] }>()

/*
 * Two independent cards: reassigning the address must not force a password
 * change and vice versa. Each keeps its own saving state, errors and success
 * message so one form can never overwrite the other's feedback.
 */
const emailEditing = ref(false)
const passwordEditing = ref(false)

const emailForm = ref({ email: '', current_password: '' })
const passwordForm = ref({ current_password: '', password: '', password_confirmation: '' })

const savingEmail = ref(false)
const savingPassword = ref(false)

const emailError = ref('')
const emailSuccess = ref('')
const passwordError = ref('')
const passwordSuccess = ref('')

const emailFieldErrors = ref<Record<string, string[]>>({})
const passwordFieldErrors = ref<Record<string, string[]>>({})

function toggleEmail(): void {
  const opening = !emailEditing.value

  emailEditing.value = opening
  emailError.value = ''
  emailSuccess.value = ''
  emailFieldErrors.value = {}

  if (opening) {
    emailForm.value = { email: props.email, current_password: '' }
  }
}

function togglePassword(): void {
  const opening = !passwordEditing.value

  passwordEditing.value = opening
  passwordError.value = ''
  passwordSuccess.value = ''
  passwordFieldErrors.value = {}

  if (opening) {
    passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
  }
}

async function saveEmail(): Promise<void> {
  savingEmail.value = true
  emailError.value = ''
  emailSuccess.value = ''
  emailFieldErrors.value = {}

  try {
    const { data } = await http.put<{ message: string; user: { email: string } }>(
      '/auth/email',
      emailForm.value,
    )

    emailSuccess.value = data.message
    emailForm.value = { email: data.user.email, current_password: '' }

    emit('changed', data.user.email)
    emailEditing.value = false
  } catch (exception) {
    if (exception instanceof ApiError) {
      emailError.value = exception.message
      emailFieldErrors.value = exception.errors
    } else {
      emailError.value = 'تعذر تحديث البريد الإلكتروني.'
    }
  } finally {
    savingEmail.value = false
  }
}

async function savePassword(): Promise<void> {
  savingPassword.value = true
  passwordError.value = ''
  passwordSuccess.value = ''
  passwordFieldErrors.value = {}

  try {
    const { data } = await http.put<{ message: string }>('/auth/password', passwordForm.value)

    passwordSuccess.value = data.message
    passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
    passwordEditing.value = false
  } catch (exception) {
    if (exception instanceof ApiError) {
      passwordError.value = exception.message
      passwordFieldErrors.value = exception.errors
    } else {
      passwordError.value = 'تعذر تغيير كلمة المرور.'
    }
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <section class="surface-card mt-6 p-6">
    <h2 class="font-extrabold text-slate-900">الحساب والأمان</h2>
    <p class="mt-1 text-sm text-slate-500">
      بيانات الدخول إلى لوحة التحكم — لا تظهر لعملائك.
    </p>

    <!-- البريد الإلكتروني -->
    <div class="mt-5 rounded-xl border border-slate-200 p-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="text-xs font-extrabold text-slate-500">البريد الإلكتروني</p>
          <p class="mt-1 break-all text-sm font-extrabold text-slate-900">{{ email }}</p>
          <p class="mt-1 text-xs leading-5 text-slate-400">
            يُستخدم لتسجيل الدخول واستقبال تنبيهات الحجوزات.
          </p>
        </div>

        <button type="button" class="btn btn-ghost shrink-0" @click="toggleEmail">
          {{ emailEditing ? 'إلغاء' : 'تغيير البريد' }}
        </button>
      </div>

      <AlertMessage v-if="emailError" type="error" :message="emailError" class="mt-4" />
      <AlertMessage v-if="emailSuccess" type="success" :message="emailSuccess" class="mt-4" />

      <form
        v-if="emailEditing"
        class="mt-4 space-y-4 border-t border-slate-100 pt-4"
        @submit.prevent="saveEmail"
      >
        <BaseInput
          v-model="emailForm.email"
          label="البريد الإلكتروني الجديد"
          type="email"
          required
          autocomplete="email"
          :error="emailFieldErrors.email?.[0] ?? ''"
        />
        <BaseInput
          v-model="emailForm.current_password"
          label="كلمة المرور الحالية"
          type="password"
          required
          autocomplete="current-password"
          hint="تأكيد بكلمة المرور يحمي حسابك حتى لو سُرق جلستك."
          :error="emailFieldErrors.current_password?.[0] ?? ''"
        />

        <div class="flex justify-end">
          <button type="submit" class="btn btn-primary" :disabled="savingEmail">
            {{ savingEmail ? 'جارٍ الحفظ...' : 'تأكيد تغيير البريد' }}
          </button>
        </div>
      </form>
    </div>

    <!-- كلمة المرور -->
    <div class="mt-4 rounded-xl border border-slate-200 p-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="text-xs font-extrabold text-slate-500">كلمة المرور</p>
          <p class="mt-1 font-mono text-sm font-extrabold tracking-[0.3em] text-slate-900" aria-hidden="true">
            ••••••••••
          </p>
          <p class="mt-1 text-xs leading-5 text-slate-400">
            محجوبة لأسباب أمنية ولا يمكن عرضها — لكن يمكنك تغييرها في أي وقت.
          </p>
        </div>

        <button type="button" class="btn btn-ghost shrink-0" @click="togglePassword">
          {{ passwordEditing ? 'إلغاء' : 'تغيير كلمة المرور' }}
        </button>
      </div>

      <AlertMessage v-if="passwordError" type="error" :message="passwordError" class="mt-4" />
      <AlertMessage v-if="passwordSuccess" type="success" :message="passwordSuccess" class="mt-4" />

      <form
        v-if="passwordEditing"
        class="mt-4 space-y-4 border-t border-slate-100 pt-4"
        @submit.prevent="savePassword"
      >
        <BaseInput
          v-model="passwordForm.current_password"
          label="كلمة المرور الحالية"
          type="password"
          required
          autocomplete="current-password"
          :error="passwordFieldErrors.current_password?.[0] ?? ''"
        />
        <BaseInput
          v-model="passwordForm.password"
          label="كلمة المرور الجديدة"
          type="password"
          required
          autocomplete="new-password"
          hint="10 أحرف على الأقل وتضم حرفاً كبيراً وصغيراً ورقماً ورمزاً."
          :error="passwordFieldErrors.password?.[0] ?? ''"
        />
        <BaseInput
          v-model="passwordForm.password_confirmation"
          label="تأكيد كلمة المرور الجديدة"
          type="password"
          required
          autocomplete="new-password"
          :error="passwordFieldErrors.password_confirmation?.[0] ?? ''"
        />

        <div class="flex justify-end">
          <button type="submit" class="btn btn-primary" :disabled="savingPassword">
            {{ savingPassword ? 'جارٍ الحفظ...' : 'تأكيد تغيير كلمة المرور' }}
          </button>
        </div>
      </form>
    </div>
  </section>
</template>
