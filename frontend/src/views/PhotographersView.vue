<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { http } from '@/api/client'
import type { PaginationMeta, Photographer } from '@/api/types'

import PhotographerCard from '@/components/photographer/PhotographerCard.vue'
import AlertMessage from '@/components/ui/AlertMessage.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Pagination from '@/components/ui/Pagination.vue'

const route = useRoute()
const router = useRouter()

const photographers = ref<Photographer[]>([])
const meta = ref<PaginationMeta | null>(null)
const loading = ref(true)
const error = ref('')

const search = ref((route.query.q as string) ?? '')
const city = ref((route.query.city as string) ?? '')

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const { data } = await http.get<{ data: Photographer[]; meta: PaginationMeta }>('/photographers', {
      params: {
        q: search.value || undefined,
        city: city.value || undefined,
        page: route.query.page ?? 1,
        per_page: 12,
      },
    })

    photographers.value = data.data
    meta.value = data.meta
  } catch {
    error.value = 'تعذر تحميل قائمة المصورين.'
  } finally {
    loading.value = false
  }
}

function syncQuery(): void {
  void router.replace({
    query: {
      ...(search.value ? { q: search.value } : {}),
      ...(city.value ? { city: city.value } : {}),
      ...(route.query.page ? { page: route.query.page } : {}),
    },
  })
}

function changePage(page: number): void {
  void router.push({ query: { ...route.query, page } })
}

let debounce: ReturnType<typeof setTimeout> | undefined

watch(search, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    void router.push({ query: { ...route.query, q: search.value || undefined, page: undefined } })
  }, 400)
})

watch(
  () => route.query,
  () => {
    search.value = (route.query.q as string) ?? ''
    city.value = (route.query.city as string) ?? ''
    void load()
  },
)

onMounted(load)
</script>

<template>
  <div class="container-page py-12">
    <header class="mb-8">
      <h1 class="text-3xl font-extrabold text-slate-900">دليل المصورين</h1>
      <p class="mt-1 text-slate-500">
        اختر المصور المناسب وابدأ الحجز مباشرة من صفحته.
      </p>
    </header>

    <div class="surface-card mb-8 grid gap-4 p-5 sm:grid-cols-3">
      <div class="sm:col-span-2">
        <BaseInput
          v-model="search"
          label="ابحث"
          placeholder="اسم المصور أو التخصص أو المدينة"
        />
      </div>
      <div class="flex items-end">
        <button type="button" class="btn btn-dark w-full" @click="syncQuery">بحث</button>
      </div>
    </div>

    <AlertMessage v-if="error" type="error" :message="error" class="mb-6" />

    <div v-if="loading">
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="index in 6" :key="index" class="surface-card h-80 animate-pulse bg-slate-100" />
      </div>
    </div>

    <template v-else>
      <div
        v-if="photographers.length"
        class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
      >
        <PhotographerCard
          v-for="photographer in photographers"
          :key="photographer.id"
          :photographer="photographer"
        />
      </div>

      <EmptyState
        v-else
        title="لا توجد نتائج مطابقة"
        description="جرّب تعديل كلمات البحث أو توسيع نطاق البحث."
      />

      <div class="mt-10">
        <Pagination
          v-if="meta"
          :current-page="meta.current_page"
          :last-page="meta.last_page"
          @change="changePage"
        />
      </div>
    </template>
  </div>
</template>
