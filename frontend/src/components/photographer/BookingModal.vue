<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import { ApiError, http } from '@/api/client'
import type { AvailabilityDay, Package } from '@/api/types'
import { formatDateTime, formatPrice } from '@/composables/useFormat'

import AlertMessage from '@/components/ui/AlertMessage.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import SlotPicker from './SlotPicker.vue'

const props = defineProps<{
  open: boolean
  photographerSlug: string
  photographerName: string
  packages: Package[]
}>()

const emit = defineEmits<{ close: []; booked: [reference: string] }>()

const days = ref<AvailabilityDay[]>([])
const loadingSlots = ref(false)
const submitting = ref(false)
const error = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const selectedPackageId = ref<string>('')
const selectedStart = ref<string | null>(null)

const form = ref({
  customer_name: '',
  customer_email: '',
  customer_phone: '',
  notes: '',
})

const packageOptions = computed(() =>
  props.packages.map((item) => ({ value: String(item.id), label: `${item.name} — ${formatPrice(item.price, item.currency)}` })),
)

watch(
  () => props.open,
  (open) => {
    if (open) {
      void loadSlots()
    }
  },
)

watch(selectedPackageId, () => {
  void loadSlots()
})

async function loadSlots(): Promise<void> {
  loadingSlots.value = true
  days.value = []

  try {
    const { data } = await http.get<{ data: { days: AvailabilityDay[] } }>(
      `/photographers/${props.photographerSlug}/availability`,
      { params: { days: 21, package_id: selectedPackageId.value || undefined } },
    )

    days.value = data.data.days
    selectedStart.value = null
  } catch (exception) {
    error.value = exception instanceof ApiError ? exception.message : 'تعذر تحميل المواعيد.'
  } finally {
    loadingSlots.value = false
  }
}

function reset(): void {
  error.value = ''
  fieldErrors.value = {}
  form.value = { customer_name: '', customer_email: '', customer_phone: '', notes: '' }
  selectedStart.value = null
  selectedPackageId.value = ''
}

function close(): void {
  reset()
  emit('close')
}

async function submit(): Promise<void> {
  if (!selectedStart.value) {
    error.value = 'يرجى اختيار الموعد المناسب أولاً.'
    return
  }

  submitting.value = true
  error.value = ''
  fieldErrors.value = {}

  try {
    const { data } = await http.post<{ data: { reference: string } }>('/bookings', {
      photographer: props.photographerSlug,
      start_at: selectedStart.value,
      package_id: selectedPackageId.value || null,
      customer_name: form.value.customer_name,
      customer_email: form.value.customer_email,
      customer_phone: form.value.customer_phone || null,
      notes: form.value.notes || null,
    })

    emit('booked', data.data.reference)
    reset()
  } catch (exception) {
    if (exception instanceof ApiError) {
      error.value = exception.message
      fieldErrors.value = exception.errors
    } else {
      error.value = 'تعذر إرسال طلب الحجز، حاول مرة أخرى.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <BaseModal :open="open" title="حجز موعد تصوير" size="lg" @close="close">
    <div class="space-y-5">
      <p class="text-sm text-slate-600">
        أنت تحجز مع <span class="font-bold text-slate-900">{{ photographerName }}</span>
      </p>

      <AlertMessage v-if="error" type="error" :message="error" />

      <div v-if="packages.length" class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <BaseSelect
            v-model="selectedPackageId"
            label="اختر الباقة (اختياري)"
            :options="packageOptions"
            placeholder="بدون باقة محدد"
          />
        </div>

        <div class="sm:col-span-2">
          <p class="field-label">الموعد المتاح</p>
          <SlotPicker
            :days="days"
            :selected-start="selectedStart"
            :loading="loadingSlots"
            @select="selectedStart = $event"
          />
        </div>

        <p
          v-if="selectedStart"
          class="sm:col-span-2 rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700"
        >
          الموعد المختار: {{ formatDateTime(selectedStart) }}
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <BaseInput
          v-model="form.customer_name"
          label="الاسم"
          required
          placeholder="الاسم الكامل"
          autocomplete="name"
          :error="fieldErrors.customer_name?.[0] ?? ''"
        />
        <BaseInput
          v-model="form.customer_email"
          label="البريد الإلكتروني"
          type="email"
          required
          placeholder="name@example.com"
          autocomplete="email"
          :error="fieldErrors.customer_email?.[0] ?? ''"
        />
        <BaseInput
          v-model="form.customer_phone"
          label="رقم الجوال (اختياري)"
          type="tel"
          placeholder="05xxxxxxxx"
          autocomplete="tel"
          :error="fieldErrors.customer_phone?.[0] ?? ''"
        />
        <BaseTextarea
          v-model="form.notes"
          label="ملاحظات (اختياري)"
          :rows="3"
          placeholder="تفاصيل إضافية عن الجلسة المطلوبة"
          :error="fieldErrors.notes?.[0] ?? ''"
        />
      </div>

      <div class="flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row-reverse">
        <button type="button" class="btn btn-primary" :disabled="submitting" @click="submit">
          {{ submitting ? 'جارٍ الإرسال...' : 'تأكيد طلب الحجز' }}
        </button>
        <button type="button" class="btn btn-ghost" :disabled="submitting" @click="close">
          إلغاء
        </button>
      </div>
    </div>
  </BaseModal>
</template>
