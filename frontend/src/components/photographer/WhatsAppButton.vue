<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    url: string | null
    label?: string
    message?: string
    variant?: 'solid' | 'outline'
  }>(),
  {
    label: 'تواصل عبر واتساب',
    message: 'مرحباً، أرغب في الاستفسار عن مواعيد التصوير المتاحة.',
    variant: 'solid',
  },
)

function buildUrl(url: string | null): string | undefined {
  if (!url) {
    return undefined
  }

  const separator = url.includes('?') ? '&' : '?'
  return `${url}${separator}text=${encodeURIComponent(props.message)}`
}
</script>

<template>
  <a
    v-if="url"
    :href="buildUrl(url)"
    target="_blank"
    rel="noopener noreferrer"
    class="btn"
    :class="variant === 'solid' ? 'bg-[#25D366] text-white hover:brightness-95' : 'btn-ghost'"
  >
    <span aria-hidden="true">💬</span>
    {{ label }}
  </a>
  <span v-else class="btn btn-ghost cursor-not-allowed opacity-60" title="لم يضبط رقم واتساب">
    <span aria-hidden="true">💬</span>
    {{ label }}
  </span>
</template>
