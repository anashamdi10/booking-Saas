<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { ApiError, http } from '@/api/client'
import type { PaginationMeta, Plan, User, UserStatus } from '@/api/types'
import { formatNumber } from '@/composables/useFormat'

import AlertMessage from '@/components/ui/AlertMessage.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import Pagination from '@/components/ui/Pagination.vue'

interface PhotographerRow {
  user: User
  profile: { slug: string; city: string | null; headline: string | null } | null
  subscription: { id: number; plan_id: number; status: string; storage_limit_bytes: number } | null
}

const rows = ref<PhotographerRow[]>([])
const plans = ref<Plan[]>([])
const meta = ref<PaginationMeta | null>(null)
const statusFilter = ref<UserStatus | ''>('')
const page = ref(1)
const loading = ref(true)
const error = ref('')
const actionError = ref('')
const busyId = ref<number | null>(null)
const selectedPlan = ref<Record<number, string>>({})

const statusOptions: { value: UserStatus | ''; label: string }[] = [
  { value: '', label: 'كل الحالات' },
  { value: 'pending', label: 'قيد المراجعة' },
  { value: 'active', label: 'نشط' },
  { value: 'suspended', label: 'موقوف' },
]

const planOptions = computed(() => [
  { value: '', label: 'بدون باقة' },
  ...plans.value.map((plan) => ({
    value: String(plan.id),
    label: `${plan.name} — ${formatNumber(plan.storage_limit_gb)} جيجابايت`,
  })),
])

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const { data } = await http.get<{ data: PhotographerRow[]; meta: PaginationMeta }>(
      '/admin/photographers',
      { params: { status: statusFilter.value || undefined, page: page.value, per_page: 15 } },
    )

    rows.value = data.data
    meta.value = data.meta

    for (const row of rows.value) {
      selectedPlan.value[row.user.id] = row.subscription ? String(row.subscription.plan_id) : ''
    }
  } catch {
    error.value = 'تعذر تحميل قائمة المصورين.'
  } finally {
    loading.value = false
  }
}

async function updateStatus(userId: number, status: UserStatus): Promise<void> {
  busyId.value = userId
  actionError.value = ''

  try {
    await http.patch(`/admin/photographers/${userId}`, { status })
    await load()
  } catch (exception) {
    actionError.value = exception instanceof ApiError ? exception.message : 'تعذر تحديث الحالة.'
  } finally {
    busyId.value = null
  }
}

async function assignPlan(userId: number): Promise<void> {
  busyId.value = userId
  actionError.value = ''

  const planId = selectedPlan.value[userId]

  try {
    await http.patch(`/admin/photographers/${userId}`, {
      plan_id: planId ? Number(planId) : null,
      subscription_status: planId ? 'active' : undefined,
    })

    await load()
  } catch (exception) {
    actionError.value = exception instanceof ApiError ? exception.message : 'تعذر إسناد الباقة.'
  } finally {
    busyId.value = null
  }
}

function statusClass(status: UserStatus): string {
  switch (status) {
    case 'active':
      return 'bg-emerald-100 text-emerald-700'
    case 'pending':
      return 'bg-amber-100 text-amber-700'
    default:
      return 'bg-rose-100 text-rose-700'
  }
}

function statusLabel(status: UserStatus): string {
  switch (status) {
    case 'active':
      return 'نشط'
    case 'pending':
      return 'قيد المراجعة'
    default:
      return 'موقوف'
  }
}

function changeFilter(): void {
  page.value = 1
  void load()
}

function changePage(next: number): void {
  page.value = next
  void load()
}

onMounted(async () => {
  try {
    const { data } = await http.get<{ data: Plan[] }>('/admin/plans')
    // The endpoint lists every plan so it can be managed; only the active
    // ones may be handed to a photographer.
    plans.value = data.data.filter((plan) => plan.is_active)
  } catch {
    plans.value = []
  }

  await load()
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold text-slate-900">إدارة المصورين</h1>
    <p class="mt-1 text-sm text-slate-500">
      راجع طلبات التسجيل، فعّل الحسابات، وأسنِد باقات الاشتراك.
    </p>

    <div class="mt-6 max-w-xs">
      <BaseSelect v-model="statusFilter" label="الحالة" :options="statusOptions" @change="changeFilter" />
    </div>

    <AlertMessage v-if="error" type="error" :message="error" class="mt-6" />
    <AlertMessage v-if="actionError" type="error" :message="actionError" class="mt-4" />

    <div v-if="loading" class="surface-card mt-6">
      <LoadingSpinner />
    </div>

    <EmptyState v-else-if="!rows.length" class="mt-6" title="لا توجد حسابات" />

    <ul v-else class="mt-6 space-y-4">
      <li v-for="row in rows" :key="row.user.id" class="surface-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-bold text-slate-900">{{ row.user.name }}</p>
              <span class="chip" :class="statusClass(row.user.status)">
                {{ statusLabel(row.user.status) }}
              </span>
              <span
                v-if="row.subscription"
                class="chip bg-slate-100 text-slate-600"
              >
                {{ formatNumber(row.subscription.storage_limit_bytes / (1024 ** 3)) }} جيجابايت
              </span>
            </div>

            <p class="mt-1 text-sm text-slate-500">{{ row.user.email }}</p>
            <p v-if="row.profile?.city" class="text-sm text-slate-500">{{ row.profile.city }}</p>
          </div>

          <div class="flex w-full flex-col gap-3 sm:w-80">
            <BaseSelect
              v-model="selectedPlan[row.user.id]"
              :options="planOptions"
              placeholder="اختر الباقة"
            />

            <div class="flex flex-wrap gap-2">
              <RouterLink
                :to="{ name: 'admin-packages', query: { photographer: String(row.user.id) } }"
                class="btn btn-ghost"
              >
                باقات التصوير
              </RouterLink>

              <button
                v-if="row.user.status !== 'active'"
                type="button"
                class="btn bg-emerald-600 text-white hover:brightness-95"
                :disabled="busyId === row.user.id"
                @click="updateStatus(row.user.id, 'active')"
              >
                تفعيل
              </button>
              <button
                v-if="row.user.status === 'active'"
                type="button"
                class="btn btn-ghost"
                :disabled="busyId === row.user.id"
                @click="updateStatus(row.user.id, 'suspended')"
              >
                إيقاف
              </button>
              <button
                type="button"
                class="btn btn-dark"
                :disabled="busyId === row.user.id || !selectedPlan[row.user.id]"
                @click="assignPlan(row.user.id)"
              >
                إسناد الباقة
              </button>
            </div>
          </div>
        </div>
      </li>
    </ul>

    <div class="mt-8">
      <Pagination
        v-if="meta"
        :current-page="meta.current_page"
        :last-page="meta.last_page"
        @change="changePage"
      />
    </div>
  </div>
</template>
