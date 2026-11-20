import { createI18n } from 'vue-i18n'
import en from './en'
import sw from './sw'

export const LOCALES = [
  { code: 'en', labelKey: 'prefs.english', short: 'EN' },
  { code: 'sw', labelKey: 'prefs.swahili', short: 'SW' },
]

const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: localStorage.getItem('rm_locale') || 'en',
  fallbackLocale: 'en',
  messages: { en, sw },
})

export default i18n
