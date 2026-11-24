import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/HomeView.vue'),
    meta: { title: 'الرئيسية' },
  },
  {
    path: '/photographers',
    name: 'photographers',
    component: () => import('@/views/PhotographersView.vue'),
    meta: { title: 'دليل المصورين' },
  },
  {
    path: '/photographers/:slug',
    name: 'photographer-profile',
    component: () => import('@/views/PhotographerProfileView.vue'),
    meta: { title: 'صفحة المصور' },
  },
  {
    path: '/booking/success',
    name: 'booking-success',
    component: () => import('@/views/BookingSuccessView.vue'),
    meta: { title: 'تم استلام طلب الحجز' },
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { title: 'تسجيل الدخول', guestOnly: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/RegisterView.vue'),
    meta: { title: 'إنشاء حساب مصور', guestOnly: true },
  },
  {
    path: '/photographer',
    component: () => import('@/views/photographer/PhotographerLayout.vue'),
    meta: { requiresAuth: true, requiresPhotographer: true },
    children: [
      {
        path: '',
        name: 'photographer-dashboard',
        component: () => import('@/views/photographer/DashboardView.vue'),
        meta: { title: 'لوحة التحكم' },
      },
      {
        path: 'bookings',
        name: 'photographer-bookings',
        component: () => import('@/views/photographer/BookingsView.vue'),
        meta: { title: 'الحجوزات' },
      },
      {
        path: 'profile',
        name: 'photographer-profile-edit',
        component: () => import('@/views/photographer/ProfileView.vue'),
        meta: { title: 'الصفحة الشخصية' },
      },
      {
        path: 'packages',
        name: 'photographer-packages',
        component: () => import('@/views/photographer/PackagesView.vue'),
        meta: { title: 'الباقات والأسعار' },
      },
    ],
  },
  {
    path: '/admin',
    component: () => import('@/views/admin/AdminLayout.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
    children: [
      {
        path: '',
        name: 'admin-dashboard',
        component: () => import('@/views/admin/DashboardView.vue'),
        meta: { title: 'لوحة الإدارة' },
      },
      {
        path: 'photographers',
        name: 'admin-photographers',
        component: () => import('@/views/admin/PhotographersView.vue'),
        meta: { title: 'إدارة المصورين' },
      },
      {
        path: 'packages',
        name: 'admin-packages',
        component: () => import('@/views/admin/PackagesView.vue'),
        meta: { title: 'باقات التصوير والأسعار' },
      },
      {
        path: 'plans',
        name: 'admin-plans',
        component: () => import('@/views/admin/PlansView.vue'),
        meta: { title: 'باقات الاشتراك' },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
    meta: { title: 'الصفحة غير موجودة' },
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior: (to, _from, savedPosition) => {
    if (savedPosition) {
      return savedPosition
    }

    if (to.hash) {
      return { el: to.hash, behavior: 'smooth' }
    }

    return { top: 0 }
  },
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (auth.user === null) {
    await auth.fetchMe()
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: auth.isAdmin ? 'admin-dashboard' : 'photographer-dashboard' }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return { name: 'home' }
  }

  if (to.meta.requiresPhotographer && !auth.isPhotographer) {
    return { name: 'home' }
  }

  return true
})

router.afterEach((to) => {
  const title = to.meta.title as string | undefined
  document.title = title ? `${title} — منصة حجوزات المصورين` : 'منصة حجوزات المصورين'
})

export default router
