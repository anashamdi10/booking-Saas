<script setup lang="ts">
import PackagesManager from '@/components/packages/PackagesManager.vue'
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold text-slate-900">الباقات والأسعار</h1>
    <p class="mt-1 text-sm text-slate-500">
      الباقات المعروضة لعملائك في صفحتك العامة ونافذة الحجز.
    </p>

    <PackagesManager class="mt-6" scope="photographer" />
  </div>
</template>
