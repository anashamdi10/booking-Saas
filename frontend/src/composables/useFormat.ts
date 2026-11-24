/**
 * Arabic aware formatting helpers shared across the application.
 */
import { computed, type ComputedRef } from 'vue'

const ARABIC_WEEKDAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت']

const CURRENCY_LABELS: Record<string, string> = {
  SAR: 'ر.س',
  AED: 'د.إ',
  EGP: 'ج.م',
  KWD: 'د.ك',
  QAR: 'ر.ق',
  USD: '$',
}

/**
 * Formats a number using the Arabic locale.
 */
export function formatNumber(value: number): string {
  return new Intl.NumberFormat('ar-EG').format(value)
}

/**
 * Formats a price with its currency label.
 */
export function formatPrice(value: number | null | undefined, currency = 'SAR'): string {
  if (value === null || value === undefined) {
    return '—'
  }

  const amount = new Intl.NumberFormat('ar-EG', {
    minimumFractionDigits: value % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  }).format(value)

  return `${amount} ${CURRENCY_LABELS[currency] ?? currency}`
}

/**
 * Formats an ISO date/time using the Arabic locale.
 */
export function formatDateTime(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }

  return new Intl.DateTimeFormat('ar-EG', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

/**
 * Formats an ISO date without the time part.
 */
export function formatDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }

  return new Intl.DateTimeFormat('ar-EG', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }).format(new Date(value))
}

/**
 * Returns the Arabic name of a weekday index (0 = Sunday).
 */
export function weekdayLabel(index: number): string {
  return ARABIC_WEEKDAYS[index] ?? ''
}

/**
 * Formats a duration given in minutes.
 */
export function formatDuration(minutes: number): string {
  if (minutes < 60) {
    return `${minutes} دقيقة`
  }

  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60

  return rest === 0 ? `${hours} ساعة` : `${hours} ساعة و ${rest} دقيقة`
}

/**
 * Converts a byte count into a human readable size.
 */
export function formatBytes(bytes: number): string {
  if (bytes <= 0) {
    return '0 جيجابايت'
  }

  const units = ['بايت', 'كيلوبايت', 'ميغابايت', 'جيجابايت', 'تيرابايت']
  const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)
  const value = bytes / 1024 ** exponent

  return `${new Intl.NumberFormat('ar-EG', { maximumFractionDigits: 2 }).format(value)} ${units[exponent]}`
}

/**
 * Composable wrapper so templates can use the helpers reactively.
 */
export function useFormat(): {
  number: ComputedRef<(value: number) => string>
  price: ComputedRef<(value: number | null | undefined, currency?: string) => string>
} {
  return {
    number: computed(() => formatNumber),
    price: computed(() => formatPrice),
  }
}
