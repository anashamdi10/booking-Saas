import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { ApiError, http, tokenStore } from '@/api/client'
import type { User } from '@/api/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isPhotographer = computed(() => user.value?.role === 'photographer')
  const isActive = computed(() => user.value?.status === 'active')

  function setUser(value: User | null): void {
    user.value = value
  }

  async function login(email: string, password: string, remember = false): Promise<boolean> {
    loading.value = true
    error.value = null

    try {
      const { data } = await http.post<{ user: User; token: string }>('/auth/login', {
        email,
        password,
        remember,
      })

      tokenStore.set(data.token)
      user.value = data.user

      return true
    } catch (exception) {
      error.value =
        exception instanceof ApiError ? exception.message : 'تعذر تسجيل الدخول، حاول مرة أخرى.'
      return false
    } finally {
      loading.value = false
    }
  }

  async function register(payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
    phone?: string
  }): Promise<boolean> {
    loading.value = true
    error.value = null

    try {
      const { data } = await http.post<{ user: User; token: string; message: string }>(
        '/auth/register',
        payload,
      )

      tokenStore.set(data.token)
      user.value = data.user

      return true
    } catch (exception) {
      error.value =
        exception instanceof ApiError ? exception.message : 'تعذر إنشاء الحساب، حاول مرة أخرى.'
      return false
    } finally {
      loading.value = false
    }
  }

  /**
   * Restores the session from the stored token on a page refresh.
   */
  async function fetchMe(): Promise<boolean> {
    if (!tokenStore.get()) {
      return false
    }

    try {
      const { data } = await http.get<{ user: User }>('/auth/me')
      user.value = data.user

      return true
    } catch {
      tokenStore.clear()
      user.value = null

      return false
    }
  }

  async function logout(): Promise<void> {
    try {
      await http.post('/auth/logout')
    } catch {
      // The local session is cleared even when the server call fails.
    } finally {
      tokenStore.clear()
      user.value = null
    }
  }

  function clearError(): void {
    error.value = null
  }

  return {
    user,
    loading,
    error,
    isAuthenticated,
    isAdmin,
    isPhotographer,
    isActive,
    setUser,
    login,
    register,
    fetchMe,
    logout,
    clearError,
  }
})
