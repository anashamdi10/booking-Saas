import axios, { AxiosError, type AxiosInstance } from 'axios'

/**
 * Error thrown by the API layer. It carries the Arabic message returned by the
 * server plus the per-field validation errors, so forms can highlight the
 * offending inputs.
 */
export class ApiError extends Error {
  readonly status: number

  readonly errors: Record<string, string[]>

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }

  get isValidation(): boolean {
    return this.status === 422
  }

  get isUnauthenticated(): boolean {
    return this.status === 401
  }
}

const TOKEN_STORAGE_KEY = 'photographer-booking.token'

/**
 * The access token lives in memory and is mirrored into sessionStorage so a
 * page refresh keeps the session alive without persisting it across browser
 * restarts.
 */
export const tokenStore = {
  get(): string | null {
    return sessionStorage.getItem(TOKEN_STORAGE_KEY)
  },
  set(token: string): void {
    sessionStorage.setItem(TOKEN_STORAGE_KEY, token)
  },
  clear(): void {
    sessionStorage.removeItem(TOKEN_STORAGE_KEY)
  },
}

export const http: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api/v1',
  timeout: 20_000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

http.interceptors.request.use((config) => {
  const token = tokenStore.get()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

http.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    const status = error.response?.status ?? 0
    const payload = (error.response?.data ?? {}) as {
      message?: string
      errors?: Record<string, string[]>
    }

    const message =
      payload.message ??
      (status === 0 ? 'تعذر الاتصال بالخادم، تحقق من اتصالك بالإنترنت.' : 'حدث خطأ غير متوقع.')

    if (status === 401) {
      tokenStore.clear()
    }

    return Promise.reject(new ApiError(message, status, payload.errors ?? {}))
  },
)
