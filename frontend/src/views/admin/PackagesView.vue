<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { ApiError, http } from '@/api/client'

import PackagesManager from '@/components/packages/PackagesManager.vue'
import AlertMessage from '@/components/ui/AlertMessage.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

interface PhotographerRow {
  user: { id: number; name: string }
  profile: { slug: string } | null
}

const route = useRoute()
const router = useRouter()

const rows = ref<PhotographerRow[]>([])
const selectedValue = ref('')
const loading = ref(true)
const error = ref('')

const options = computed(() =>
  rows.value.map((row) => ({ value: String(row.user.id), label: row.user.name })),
)

const selectedId = computed(() => (selectedValue.value === '' ? null : Number(selectedValue.value)))

const selected = computed(() => rows.value.find((row) => row.user.id === selectedId.value) ?? null)

/**
 * The choice is mirrored into the URL, so a photographer's packages can be
 * linked to straight from the admin list and survive a reload.
 */
watch(selectedValue, (value) => {
  void router.replace({ query: value === '' ? {} : { photographer: value } })
})

onMounted(async () => {
  try {
    const { data } = await http.get<{ data: PhotographerRow[] }>('/admin/photographers', {
      params: { per_page: 60 },
    })

    rows.value = data.data

    const requested = route.query.photographer
    if (
      typeof requested === 'string' &&
      rows.value.some((row) => String(row.user.id) === requested)
    ) {
      selectedValue.value = requested
    }
  } catch (exception) {
    error.value = exception instanceof ApiError ? exception.message : 'تعذر تحميل قائمة المصورين.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold text-slate-900">باقات التصوير والأسعار</h1>
    <p class="mt-1 text-sm text-slate-500">
      أضف أو عدّل أو احذف باقات وأسعار أي مصور — وتعمل فوراً في صفحته العامة ونافذة الحجز.
    </p>

    <AlertMessage v-if="error" type="error" :message="error" class="mt-6" />

    <div v-if="loading" class="surface-card mt-6">
      <LoadingSpinner label="جارٍ تحميل المصورين..." />
    </div>

    <template v-else>
      <div class="surface-card mt-6 p-5">
        <BaseSelect
          v-model="selectedValue"
          label="المصور"
          placeholder="— اختر مصوراً —"
          :options="options"
        />

        <p class="mt-2 text-xs leading-5 text-slate-500">
          تُدار الباقات باسم المصور المختار، وتظهر لعملائه في صفحته العامة
          وفي نافذة الحجز.
        </p>

        <RouterLink
          v-if="selected?.profile?.slug"
          :to="{ name: 'photographer-profile', params: { slug: selected.profile.slug } }"
          target="photographer-preview"
          class="mt-3 inline-block text-sm font-semibold text-brand-600 hover:underline"
        >
          فتح الصفحة العامة لعرض الباقات ↗
        </RouterLink>
      </div>

      <PackagesManager
        v-if="selectedId !== null"
        :key="selectedId"
        class="mt-6"
        scope="admin"
        :photographer-id="selectedId"
      />

      <EmptyState
        v-else
        class="mt-6"
        title="اختر مصوراً أولاً"
        description="اختر اسماً من القائمة أعلاه لعرض باقاته وإدارتها."
      />
    </template>
  </div>
</template>
