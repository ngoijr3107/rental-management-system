import axios from 'axios'
import i18n from '@/i18n'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: { Accept: 'application/json' },
  // A request that never returns is worse than one that fails: the spinner
  // stays up and the person has nothing to act on.
  timeout: 20000,
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('rm_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  // Let Laravel return validation messages in the chosen language if it ever
  // gains translations of its own.
  config.headers['Accept-Language'] = i18n.global.locale.value
  return config
})

// A dropped session should land the user on the sign-in screen, not a blank page.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && !error.config?.url?.includes('/login')) {
      localStorage.removeItem('rm_token')
      if (!window.location.hash.includes('/login')) {
        window.location.hash = '#/login'
      }
    }
    return Promise.reject(error)
  },
)

/** Field-level validation errors from Laravel, keyed by input name. */
export function fieldErrors(error) {
  return error?.response?.data?.errors ?? {}
}

/** One human-readable line explaining a failed request. */
export function errorMessage(error, fallback = '') {
  const t = i18n.global.t
  const data = error?.response?.data

  if (data?.errors) {
    const first = Object.values(data.errors)[0]
    if (Array.isArray(first) && first.length) return first[0]
  }
  if (data?.message && error.response?.status !== 500) return data.message
  if (error?.response?.status === 404) return t('errors.notFound')
  if (error?.code === 'ECONNABORTED') return t('errors.timeout')
  if (error?.response?.status === 429) return t('errors.throttled')
  if (error?.response?.status >= 500) return t('errors.server')
  if (!error?.response) return t('errors.offline')
  return fallback || t('errors.generic')
}

export default api
