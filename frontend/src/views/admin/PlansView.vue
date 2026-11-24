<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { ApiError, http } from '@/api/client'
import type { Plan } from '@/api/types'
import { formatNumber, formatPrice } from '@/composables/useFormat'

import AlertMessage from '@/components/ui/AlertMessage.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

interface PlanForm {
  name: string
  description: string
  price: string
  currency: string
  storage_limit_gb: string
  max_galleries: string
  max_clients: string
  max_bookings_per_month: string
  features: string[]
  is_active: boolean
  sort_order: string
}

const plans = ref<Plan[]>([])
const loading = ref(true)
const saving = ref(false)
const togglingId = ref<number | null>(null)
const deletingId = ref<number | null>(null)

const error = ref('')
const success = ref('')

const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const pendingDeleteId = ref<number | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})

const form = ref<PlanForm>(blankForm())

const currencyOptions = [
  { value: 'SAR', label: 'ر.س — ريال سعودي' },
  { value: 'AED', label: 'د.إ — درهم إماراتي' },
  { value: 'EGP', label: 'ج.م — جنيه مصري' },
  { value: 'KWD', label: 'د.ك — دينار كويتي' },
  { value: 'QAR', label: 'ر.ق — ريال قطري' },
  { value: 'USD', label: '$ — دولار' },
]

function blankForm(): PlanForm {
  return {
    name: '',
    description: '',
    price: '',
    currency: 'SAR',
    storage_limit_gb: '5',
    max_galleries: '',
    max_clients: '',
    max_bookings_per_month: '',
    features: [],
    is_active: true,
    sort_order: '0',
  }
}

/**
 * Plans are what the admin hands to a photographer, so this screen lists
 * every plan — including retired ones — while the assignment dropdowns only
 * offer the active ones.
 */
async function load(): Promise<void> {
  try {
    const { data } = await http.get<{ data: Plan[] }>('/admin/plans')

    plans.value = data.data
    error.value = ''
  } catch (exception) {
    error.value = exception instanceof ApiError ? exception.message : 'تعذر تحميل الباقات.'
  } finally {
    loading.value = false
  }
}

function openCreate(): void {
  editingId.value = null
  form.value = blankForm()
  fieldErrors.value = {}
  modalOpen.value = true
}

function openEdit(item: Plan): void {
  editingId.value = item.id
  form.value = {
    name: item.name,
    description: item.description ?? '',
    price: String(item.price),
    currency: item.currency,
    storage_limit_gb: String(item.storage_limit_gb),
    max_galleries: item.max_galleries === null ? '' : String(item.max_galleries),
    max_clients: item.max_clients === null ? '' : String(item.max_clients),
    max_bookings_per_month:
      item.max_bookings_per_month === null ? '' : String(item.max_bookings_per_month),
    features: [...item.features],
    is_active: item.is_active,
    sort_order: String(item.sort_order),
  }
  fieldErrors.value = {}
  pendingDeleteId.value = null
  modalOpen.value = true
}

function addFeature(): void {
  form.value.features.push('')
}

function removeFeature(index: number): void {
  form.value.features.splice(index, 1)
}

/** Mirrors the server rules so mistakes are caught before the request. */
function validate(): boolean {
  const errors: Record<string, string[]> = {}

  if (form.value.name.trim().length < 2) {
    errors.name = ['أدخل اسماً للباقة لا يقل عن حرفين.']
  }

  const price = Number(form.value.price)
  if (form.value.price === '' || !Number.isFinite(price) || price < 0) {
    errors.price = ['أدخل سعراً صحيحاً.']
  }

  const storage = Number(form.value.storage_limit_gb)
  if (form.value.storage_limit_gb === '' || !Number.isFinite(storage) || storage < 0.1) {
    errors.storage_limit_gb = ['أدخل مساحة أكبر من صفر جيجابايت.']
  }

  for (const [key, message] of [
    ['max_galleries', 'أدخل عدداً صحيحاً أكبر من صفر، أو اترك الحقل فارغاً.'],
    ['max_clients', 'أدخل عدداً صحيحاً أكبر من صفر، أو اترك الحقل فارغاً.'],
    ['max_bookings_per_month', 'أدخل عدداً صحيحاً أكبر من صفر، أو اترك الحقل فارغاً.'],
  ] as const) {
    const raw = form.value[key]
    if (raw !== '') {
      const value = Number(raw)
      if (!Number.isInteger(value) || value < 0) {
        errors[key] = [message]
      }
    }
  }

  fieldErrors.value = errors

  return Object.keys(errors).length === 0
}

function payload(): Record<string, unknown> {
  const optional = (raw: string): number | null => (raw === '' ? null : Number(raw))

  return {
    name: form.value.name.trim(),
    description: form.value.description.trim() === '' ? null : form.value.description.trim(),
    price: Number(form.value.price),
    currency: form.value.currency,
    storage_limit_gb: Number(form.value.storage_limit_gb),
    max_galleries: optional(form.value.max_galleries),
    max_clients: optional(form.value.max_clients),
    max_bookings_per_month: optional(form.value.max_bookings_per_month),
    features: form.value.features.map((item) => item.trim()).filter((item) => item !== ''),
    is_active: form.value.is_active,
    sort_order: Number(form.value.sort_order),
  }
}

async function save(): Promise<void> {
  if (!validate()) return

  saving.value = true
  error.value = ''
  success.value = ''

  try {
    if (editingId.value === null) {
      await http.post('/admin/plans', payload())
      success.value = 'تمت إضافة الباقة.'
    } else {
      await http.put(`/admin/plans/${editingId.value}`, payload())
      success.value = 'تم تحديث الباقة.'
    }

    modalOpen.value = false
    await load()
  } catch (exception) {
    if (exception instanceof ApiError) {
      error.value = exception.message
      fieldErrors.value = exception.errors
    } else {
      error.value = 'تعذر حفظ الباقة.'
    }
  } finally {
    saving.value = false
  }
}

/**
 * Switching a plan off retires it from every assignment dropdown without
 * touching the subscriptions that already use it.
 */
async function toggleActive(item: Plan): Promise<void> {
  togglingId.value = item.id
  error.value = ''
  success.value = ''

  try {
    await http.put(`/admin/plans/${item.id}`, {
      name: item.name,
      description: item.description,
      price: item.price,
      currency: item.currency,
      storage_limit_gb: item.storage_limit_gb,
      max_galleries: item.max_galleries,
      max_clients: item.max_clients,
      max_bookings_per_month: item.max_bookings_per_month,
      features: item.features,
      is_active: !item.is_active,
      sort_order: item.sort_order,
    })

    success.value = item.is_active ? 'تم إيقاف الباقة.' : 'تم تفعيل الباقة.'
    await load()
  } catch (exception) {
    error.value = exception instanceof ApiError ? exception.message : 'تعذر تحديث الباقة.'
  } finally {
    togglingId.value = null
  }
}

async function remove(id: number): Promise<void> {
  deletingId.value = id
  error.value = ''
  success.value = ''

  try {
    await http.delete(`/admin/plans/${id}`)

    success.value = 'تم حذف الباقة.'
    await load()
  } catch (exception) {
    error.value = exception instanceof ApiError ? exception.message : 'تعذر حذف الباقة.'
  } finally {
    deletingId.value = null
    pendingDeleteId.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold text-slate-900">باقات الاشتراك</h1>
    <p class="mt-1 text-sm text-slate-500">
      ما يُسنَد للمصورين ويحدّد مساحة تخزينهم. إن كانت الباقة مرتبطة باشتراكات فقفِفها بدل
      حذفها، فيبقى سجل المشتركين سليماً.
    </p>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
      <p class="text-sm text-slate-500">
        الباقة الموقوفة تختفي من قوائم الإسناد فوراً، ولا تُحذف الاشتراكات القائمة عليها.
      </p>

      <button type="button" class="btn btn-dark shrink-0" @click="openCreate">
        + إضافة باقة اشتراك
      </button>
    </div>

    <AlertMessage v-if="error" type="error" :message="error" class="mt-6" />
    <AlertMessage v-if="success" type="success" :message="success" class="mt-6" />

    <div v-if="loading" class="surface-card mt-6">
      <LoadingSpinner label="جارٍ تحميل الباقات..." />
    </div>

    <EmptyState
      v-else-if="plans.length === 0"
      class="mt-6"
      title="لا توجد باقات اشتراك"
      description="أضف باقة واحدة على الأقل ليتمكن الأدمن من إسنادها للمصورين."
    />

    <ul v-else class="mt-6 space-y-4">
      <li v-for="item in plans" :key="item.id" class="surface-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="font-extrabold text-slate-900">{{ item.name }}</h2>
              <span
                class="chip"
                :class="
                  item.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
                "
              >
                {{ item.is_active ? 'نشطة' : 'موقوفة' }}
              </span>
              <span v-if="(item.subscriptions_count ?? 0) > 0" class="chip bg-sky-50 text-sky-700">
                {{ formatNumber(item.subscriptions_count ?? 0) }} اشتراك
              </span>
            </div>

            <p v-if="item.description" class="mt-1 text-sm text-slate-500">
              {{ item.description }}
            </p>

            <p class="mt-3 text-sm text-slate-600">
              <span class="text-base font-extrabold text-slate-900">
                {{ formatPrice(item.price, item.currency) }}
              </span>
              شهرياً · {{ formatNumber(item.storage_limit_gb) }} جيجابايت تخزين
              <template v-if="item.max_galleries !== null">
                · حتى {{ formatNumber(item.max_galleries) }} معرض
              </template>
              <template v-if="item.max_clients !== null">
                · حتى {{ formatNumber(item.max_clients) }} عميل
              </template>
              <template v-if="item.max_bookings_per_month !== null">
                · حتى {{ formatNumber(item.max_bookings_per_month) }} حجز شهرياً
              </template>
            </p>

            <ul v-if="item.features.length" class="mt-3 flex flex-wrap gap-2">
              <li
                v-for="feature in item.features"
                :key="feature"
                class="rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-600"
              >
                {{ feature }}
              </li>
            </ul>
          </div>

          <div class="flex shrink-0 flex-wrap gap-2">
            <button type="button" class="btn btn-ghost" @click="openEdit(item)">تعديل</button>
            <button
              type="button"
              class="btn btn-ghost"
              :disabled="togglingId === item.id"
              @click="toggleActive(item)"
            >
              {{
                togglingId === item.id
                  ? 'جارٍ التحديث...'
                  : item.is_active
                    ? 'إيقاف الباقة'
                    : 'تفعيل الباقة'
              }}
            </button>
            <button
              v-if="(item.subscriptions_count ?? 0) === 0"
              type="button"
              class="btn btn-ghost text-rose-600 hover:text-rose-700"
              @click="pendingDeleteId = pendingDeleteId === item.id ? null : item.id"
            >
              حذف
            </button>
          </div>
        </div>

        <p
          v-if="(item.subscriptions_count ?? 0) > 0"
          class="mt-4 rounded-xl bg-sky-50 px-4 py-3 text-sm font-semibold text-sky-700"
        >
          مرتبطة بـ{{ formatNumber(item.subscriptions_count ?? 0) }} اشتراك — أوقفها بدل الحذف
          حتى لا يضيع سجل المشتركين.
        </p>

        <div
          v-if="pendingDeleteId === item.id"
          class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-rose-50 px-4 py-3"
        >
          <p class="text-sm font-semibold text-rose-700">
            حذف «{{ item.name }}» نهائياً؟ لا يمكن التراجع.
          </p>

          <div class="flex gap-2">
            <button type="button" class="btn btn-ghost" @click="pendingDeleteId = null">
              تراجع
            </button>
            <button
              type="button"
              class="btn btn-ghost bg-rose-600 text-white hover:bg-rose-700"
              :disabled="deletingId === item.id"
              @click="remove(item.id)"
            >
              {{ deletingId === item.id ? 'جارٍ الحذف...' : 'حذف نهائي' }}
            </button>
          </div>
        </div>
      </li>
    </ul>

    <BaseModal
      :open="modalOpen"
      :title="editingId === null ? 'إضافة باقة اشتراك' : 'تعديل باقة الاشتراك'"
      size="md"
      @close="modalOpen = false"
    >
      <form class="space-y-4" novalidate @submit.prevent="save">
        <BaseInput
          v-model="form.name"
          label="اسم الباقة"
          required
          placeholder="مثال: باقة الاستوديو"
          :error="fieldErrors.name?.[0] ?? ''"
        />

        <BaseTextarea
          v-model="form.description"
          label="الوصف"
          :rows="3"
          placeholder="لمن هذه الباقة؟"
          :error="fieldErrors.description?.[0] ?? ''"
        />

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput
            v-model="form.price"
            label="السعر الشهري"
            type="number"
            required
            min="0"
            step="0.01"
            placeholder="349"
            :error="fieldErrors.price?.[0] ?? ''"
          />

          <BaseSelect
            v-model="form.currency"
            label="العملة"
            :options="currencyOptions"
            :error="fieldErrors.currency?.[0] ?? ''"
          />
        </div>

        <BaseInput
          v-model="form.storage_limit_gb"
          label="مساحة التخزين (جيجابايت)"
          type="number"
          required
          min="0.1"
          step="0.1"
          placeholder="25"
          :error="fieldErrors.storage_limit_gb?.[0] ?? ''"
        />

        <div class="grid gap-4 sm:grid-cols-3">
          <BaseInput
            v-model="form.max_galleries"
            label="حد المعارض (اختياري)"
            type="number"
            min="0"
            placeholder="بلا حد"
            :error="fieldErrors.max_galleries?.[0] ?? ''"
          />

          <BaseInput
            v-model="form.max_clients"
            label="حد العملاء (اختياري)"
            type="number"
            min="0"
            placeholder="بلا حد"
            :error="fieldErrors.max_clients?.[0] ?? ''"
          />

          <BaseInput
            v-model="form.max_bookings_per_month"
            label="حد الحجوزات شهرياً (اختياري)"
            type="number"
            min="0"
            placeholder="بلا حد"
            :error="fieldErrors.max_bookings_per_month?.[0] ?? ''"
          />
        </div>

        <div>
          <p class="field-label">مميزات الباقة</p>

          <div v-for="(_, index) in form.features" :key="index" class="mt-2 flex items-center gap-2">
            <input
              v-model="form.features[index]"
              type="text"
              class="field-input min-w-0 flex-1"
              maxlength="120"
              placeholder="مثال: صفحة شخصية"
            />
            <button type="button" class="btn btn-ghost" @click="removeFeature(index)">
              إزالة
            </button>
          </div>

          <button type="button" class="btn btn-ghost mt-3" @click="addFeature">
            + إضافة ميزة
          </button>

          <p v-if="fieldErrors.features?.[0]" class="field-error">
            {{ fieldErrors.features[0] }}
          </p>
        </div>

        <label class="flex items-center gap-3 text-sm font-semibold text-slate-700">
          <input
            v-model="form.is_active"
            type="checkbox"
            class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
          />
          باقة نشطة — تظهر في قوائم إسناد الباقات للمصورين
        </label>

        <BaseInput
          v-model="form.sort_order"
          label="الترتيب"
          type="number"
          min="0"
          max="9999"
          placeholder="0"
          :error="fieldErrors.sort_order?.[0] ?? ''"
        />

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn btn-ghost" @click="modalOpen = false">إلغاء</button>
          <button type="submit" class="btn btn-dark" :disabled="saving">
            {{
              saving
                ? 'جارٍ الحفظ...'
                : editingId === null
                  ? 'إضافة الباقة'
                  : 'حفظ التغييرات'
            }}
          </button>
        </div>
      </form>
    </BaseModal>
  </div>
</template>
