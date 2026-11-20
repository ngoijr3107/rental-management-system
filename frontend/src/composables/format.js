import i18n from '@/i18n'
import { useAuthStore } from '@/stores/auth'

const FALLBACK_CURRENCY = import.meta.env.VITE_CURRENCY || 'USD'

/** The account setting wins; the env value is only a fallback before load. */
function currency() {
  try {
    return useAuthStore().settings?.currency || FALLBACK_CURRENCY
  } catch {
    return FALLBACK_CURRENCY
  }
}

/** Swahili dates and numbers read better with the Tanzanian regional format. */
const INTL_LOCALE = { en: 'en-GB', sw: 'sw-TZ' }

function locale() {
  return INTL_LOCALE[i18n.global.locale.value] || 'en-GB'
}

function money(exact) {
  return new Intl.NumberFormat(locale(), {
    style: 'currency',
    currency: currency(),
    minimumFractionDigits: exact ? 2 : 0,
    maximumFractionDigits: exact ? 2 : 0,
  })
}

/** Amounts arrive as decimal strings from the API. */
export function formatMoney(value, exact = false) {
  const n = Number(value ?? 0)
  if (Number.isNaN(n)) return '—'
  return money(exact).format(n)
}

export function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleDateString(locale(), { day: '2-digit', month: 'short', year: 'numeric' })
}

export function formatMonth(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleDateString(locale(), { month: 'long', year: 'numeric' })
}

export function currentMonth() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

export function today() {
  return new Date().toISOString().slice(0, 10)
}

/** Days until (positive) or since (negative) a date. */
export function daysFromToday(value) {
  if (!value) return null
  const target = new Date(value)
  const now = new Date()
  target.setHours(0, 0, 0, 0)
  now.setHours(0, 0, 0, 0)
  return Math.round((target - now) / 86400000)
}
