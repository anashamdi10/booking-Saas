<script setup lang="ts">
import { onBeforeUnmount, onMounted, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    open: boolean
    title?: string
    size?: 'sm' | 'md' | 'lg'
  }>(),
  { title: '', size: 'md' },
)

const emit = defineEmits<{ close: [] }>()

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.open) {
    emit('close')
  }
}

watch(
  () => props.open,
  (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
  },
)

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
        role="dialog"
        aria-modal="true"
        @click.self="emit('close')"
      >
        <div
          class="surface-card max-h-[92vh] w-full overflow-y-auto rounded-b-none sm:rounded-2xl"
          :class="{
            'sm:max-w-md': size === 'sm',
            'sm:max-w-xl': size === 'md',
            'sm:max-w-3xl': size === 'lg',
          }"
        >
          <header
            v-if="title"
            class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white px-5 py-4"
          >
            <h2 class="text-base font-bold text-slate-900">{{ title }}</h2>
            <button
              type="button"
              class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
              aria-label="إغلاق"
              @click="emit('close')"
            >
              ✕
            </button>
          </header>

          <div class="px-5 py-5">
            <slot />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.18s ease;
}

.modal-enter-active > div,
.modal-leave-active > div {
  transition: transform 0.18s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from > div,
.modal-leave-to > div {
  transform: translateY(12px);
}
</style>
