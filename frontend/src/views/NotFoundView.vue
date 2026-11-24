<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="container-page grid min-h-[60vh] place-items-center py-16">
    <div class="text-center">
      <p class="text-6xl font-extrabold text-slate-300">404</p>
      <h1 class="mt-4 text-2xl font-extrabold text-slate-900">الصفحة غير موجودة</h1>
      <p class="mt-2 text-slate-500">ربما تم نقل الصفحة أو أن الرابط غير صحيح.</p>

      <RouterLink to="/" class="btn btn-primary mt-8">العودة للرئيسية</RouterLink>
    </div>
  </div>
</template>
