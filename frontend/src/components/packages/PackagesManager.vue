<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { ApiError, http } from '@/api/client'
import type { Package } from '@/api/types'
import { formatDuration, formatPrice } from '@/composables/useFormat'

import AlertMessage from '@/components/ui/AlertMessage.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const props = defineProps<{
  /** The photographer's own dashboard, or the admin panel acting for them. */
  scope: 'photographer' | 'admin'
  /** Required in the admin scope: whose packages are being managed. */
  photographerId?: number
}>()

interface PackageForm {
  name: string
  description: string
  price: string
  currency: string
  duration_minutes: string
  max_photos: string
  deliverables: string[]
  is_active: boolean
  sort_order: string
}

/*
 * Both scopes expose the same collection shape — list and create on the
 * base, update and delete on an item beneath it — so only the base differs.
 * Empty while the admin panel has no photographer selected yet.
 */
const base = computed(() =>
  props.scope === 'admin'
    ? props.photographerId === undefined
      ? ''
      : `/admin/photographers/${props.photographerId}/packages`
    : '/photographer/packages',
)

const hint = computed(() =>
  props.scope === 'admin'
    ? 'تعمل الباقات فوراً في صفحة المصور العامة وفي نافذة الحجز.'
    : 'أسعارك تخصّك وحدك ولا تُشارك مع أي مصور آخر.',
)

const packages = ref<Package[]>([])
const loading = ref(true)
const saving = ref(false)
const deletingId = ref<number | null>(null)

const error = ref('')
const success = ref('')

const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const pendingDeleteId = ref<number | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})

const form = ref<PackageForm>(blankForm())

const currencyOptions = [
  { value: 'SAR', label: 'ر.س — ريال سعودي' },
  { value: 'AED', label: 'د.إ — درهم إماراتي' },
  { value: 'EGP', label: 'ج.م — جنيه مصري' },
  { value: 'KWD', label: 'د.ك — دينار كويتي' },
  { value: 'QAR', label: 'ر.ق — ريال قطري' },
  { value: 'USD', label: '$ — دولار' },
]

function blankForm(): PackageForm {
  return {
    name: '',
    description: '',
    price: '',
    currency: 'SAR',
    duration_minutes: '60',
    max_photos: '',
    deliverables: [],
    is_active: true,
    sort_order: '0',
  }
}

/**
 * In the photographer scope these are always the caller's own packages, and
 * in the admin scope the server re-checks that the package belongs to the
 * photographer named in the path — so prices stay isolated either way.
 */
async function load(): Promise<void> {
  if (base.value === '') return

  try {
    const { data } = await http.get<{ data: Package[] }>(base.value)

    packages.value = data.data
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

function openEdit(item: Package): void {
  editingId.value = item.id
  form.value = {
    name: item.name,
    description: item.description ?? '',
    price: String(item.price),
    currency: item.currency,
    duration_minutes: String(item.duration_minutes),
    max_photos: item.max_photos === null ? '' : String(item.max_photos),
    deliverables: [...item.deliverables],
    is_active: item.is_active,
    sort_order: String(item.sort_order),
  }
  fieldErrors.value = {}
  pendingDeleteId.value = null
  modalOpen.value = true
}

function addDeliverable(): void {
  form.value.deliverables.push('')
}

function removeDeliverable(index: number): void {
  form.value.deliverables.splice(index, 1)
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

  const duration = Number(form.value.duration_minutes)
  if (!Number.isInteger(duration) || duration < 15 || duration > 1440) {
    errors.duration_minutes = ['المدة يجب أن تكون بين 15 دقيقة و 24 ساعة.']
  }

  if (form.value.max_photos !== '') {
    const photos = Number(form.value.max_photos)
    if (!Number.isInteger(photos) || photos < 1) {
      errors.max_photos = ['أدخل عدداً صحيحاً أكبر من صفر، أو اترك الحقل فارغاً.']
    }
  }

  fieldErrors.value = errors

  return Object.keys(errors).length === 0
}

async function save(): Promise<void> {
  if (!validate() || base.value === '') return

  saving.value = true
  error.value = ''
  success.value = ''

  const payload = {
    name: form.value.name.trim(),
    description: form.value.description.trim() === '' ? null : form.value.description.trim(),
    price: Number(form.value.price),
    currency: form.value.currency,
    duration_minutes: Number(form.value.duration_minutes),
    max_photos: form.value.max_photos === '' ? null : Number(form.value.max_photos),
    deliverables: form.value.deliverables.map((item) => item.trim()).filter((item) => item !== ''),
    is_active: form.value.is_active,
    sort_order: Number(form.value.sort_order),
  }

  try {
    if (editingId.value === null) {
      await http.post(base.value, payload)
      success.value = 'تمت إضافة الباقة.'
    } else {
      await http.put(`${base.value}/${editingId.value}`, payload)
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

async function remove(id: number): Promise<void> {
  if (base.value === '') return

  deletingId.value = id
  error.value = ''
  success.value = ''

  try {
    await http.delete(`${base.value}/${id}`)

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
  if (base.value !== '') void load()
})
</script>

<template>
  <div v-if="base !== ''">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <p class="text-sm text-slate-500">{{ hint }}</p>

      <button type="button" class="btn btn-dark shrink-0" @click="openCreate">
        + إضافة باقة
      </button>
    </div>

    <AlertMessage v-if="error" type="error" :message="error" class="mt-6" />
    <AlertMessage v-if="success" type="success" :message="success" class="mt-6" />

    <div v-if="loading" class="surface-card mt-6">
      <LoadingSpinner label="جارٍ تحميل الباقات..." />
    </div>

    <EmptyState
      v-else-if="packages.length === 0"
      class="mt-6"
      title="لا توجد باقات بعد"
      description="أضف باقة واحدة على الأقل ليتمكن العميل من اختيارها عند الحجز."
    />

    <ul v-else class="mt-6 space-y-4">
      <li v-for="item in packages" :key="item.id" class="surface-card p-5">
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
            </div>

            <p v-if="item.description" class="mt-1 text-sm text-slate-500">
              {{ item.description }}
            </p>

            <p class="mt-3 text-sm text-slate-600">
              <span class="text-base font-extrabold text-slate-900">
                {{ formatPrice(item.price, item.currency) }}
              </span>
              · {{ formatDuration(item.duration_minutes) }}
              <template v-if="item.max_photos">
                · حتى {{ item.max_photos }} صورة
              </template>
            </p>

            <ul v-if="item.deliverables.length" class="mt-3 flex flex-wrap gap-2">
              <li
                v-for="deliverable in item.deliverables"
                :key="deliverable"
                class="rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-600"
              >
                {{ deliverable }}
              </li>
            </ul>
          </div>

          <div class="flex shrink-0 gap-2">
            <button type="button" class="btn btn-ghost" @click="openEdit(item)">تعديل</button>
            <button
              type="button"
              class="btn btn-ghost text-rose-600 hover:text-rose-700"
              @click="pendingDeleteId = pendingDeleteId === item.id ? null : item.id"
            >
              حذف
            </button>
          </div>
        </div>

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
      :title="editingId === null ? 'إضافة باقة' : 'تعديل الباقة'"
      size="md"
      @close="modalOpen = false"
    >
      <form class="space-y-4" novalidate @submit.prevent="save">
        <BaseInput
          v-model="form.name"
          label="اسم الباقة"
          required
          placeholder="مثال: جلسة تصوير عائلية"
          :error="fieldErrors.name?.[0] ?? ''"
        />

        <BaseTextarea
          v-model="form.description"
          label="الوصف"
          :rows="3"
          placeholder="ماذا يحصل عليه العميل بالضبط؟"
          :error="fieldErrors.description?.[0] ?? ''"
        />

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput
            v-model="form.price"
            label="السعر"
            type="number"
            required
            min="0"
            step="0.01"
            placeholder="450"
            :error="fieldErrors.price?.[0] ?? ''"
          />

          <BaseSelect
            v-model="form.currency"
            label="العملة"
            :options="currencyOptions"
            :error="fieldErrors.currency?.[0] ?? ''"
          />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput
            v-model="form.duration_minutes"
            label="المدة (بالدقائق)"
            type="number"
            required
            min="15"
            max="1440"
            placeholder="60"
            :error="fieldErrors.duration_minutes?.[0] ?? ''"
          />

          <BaseInput
            v-model="form.max_photos"
            label="عدد الصور (اختياري)"
            type="number"
            min="1"
            max="10000"
            placeholder="60"
            :error="fieldErrors.max_photos?.[0] ?? ''"
          />
        </div>

        <div>
          <p class="field-label">مميزات الباقة</p>

          <div
            v-for="(_, index) in form.deliverables"
            :key="index"
            class="mt-2 flex items-center gap-2"
          >
            <input
              v-model="form.deliverables[index]"
              type="text"
              class="field-input min-w-0 flex-1"
              maxlength="120"
              placeholder="مثال: صور عالية الدقة"
            />
            <button type="button" class="btn btn-ghost" @click="removeDeliverable(index)">
              إزالة
            </button>
          </div>

          <button type="button" class="btn btn-ghost mt-3" @click="addDeliverable">
            + إضافة ميزة
          </button>

          <p v-if="fieldErrors.deliverables?.[0]" class="field-error">
            {{ fieldErrors.deliverables[0] }}
          </p>
        </div>

        <label class="flex items-center gap-3 text-sm font-semibold text-slate-700">
          <input
            v-model="form.is_active"
            type="checkbox"
            class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
          />
          باقة نشطة — تظهر للعميل ويسمح الحجز عليها
        </label>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn btn-ghost" @click="modalOpen = false">إلغاء</button>
          <button type="submit" class="btn btn-dark" :disabled="saving">
            {{ saving ? 'جارٍ الحفظ...' : editingId === null ? 'إضافة الباقة' : 'حفظ التغييرات' }}
          </button>
        </div>
      </form>
    </BaseModal>
  </div>
</template>
