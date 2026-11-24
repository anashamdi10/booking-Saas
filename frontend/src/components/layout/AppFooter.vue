<script setup lang="ts">
import { RouterLink } from 'vue-router'

const year = new Date().getFullYear()
</script>

<template>
  <footer class="mt-16 border-t border-slate-200 bg-white">
    <div class="container-page flex flex-col gap-6 py-10 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="font-extrabold text-slate-900">منصة حجوزات المصورين</p>
        <p class="mt-1 text-sm text-slate-500">
          احجز موعد التصوير الخاص بك مع أفضل المصورين في دقائق.
        </p>
      </div>

      <nav class="flex flex-wrap items-center gap-4 text-sm font-semibold text-slate-600">
        <RouterLink to="/photographers" class="transition hover:text-slate-900">دليل المصورين</RouterLink>
        <RouterLink to="/register" class="transition hover:text-slate-900">انضم كمصور</RouterLink>
        <RouterLink to="/login" class="transition hover:text-slate-900">تسجيل الدخول</RouterLink>
      </nav>
    </div>

    <div class="border-t border-slate-100">
      <p class="container-page py-4 text-center text-xs text-slate-400">
        © {{ year }} منصة حجوزات المصورين — جميع الحقوق محفوظة.
      </p>
    </div>
  </footer>
</template>
