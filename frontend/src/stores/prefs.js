import { defineStore } from 'pinia'

const THEME_KEY = 'rm_theme'
const LOCALE_KEY = 'rm_locale'
const PALETTE_KEY = 'rm_palette'
const RAIL_KEY = 'rm_rail'

export const PALETTES = [
  { id: 'corporate', labelKey: 'prefs.paletteCorporate', swatch: '#0EA5E9' },
  { id: 'eco', labelKey: 'prefs.paletteEco', swatch: '#10B981' },
  { id: 'studio', labelKey: 'prefs.paletteStudio', swatch: '#5E3A73' },
]

const prefersDark = () =>
  window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches

export const usePrefsStore = defineStore('prefs', {
  state: () => ({
    // 'light' | 'dark' | 'system'
    theme: localStorage.getItem(THEME_KEY) || 'system',
    palette: localStorage.getItem(PALETTE_KEY) || 'corporate',
    locale: localStorage.getItem(LOCALE_KEY) || 'en',
    railCollapsed: localStorage.getItem(RAIL_KEY) === '1',
  }),

  getters: {
    /** What is actually on screen right now. */
    resolvedTheme: (state) =>
      state.theme === 'system' ? (prefersDark() ? 'dark' : 'light') : state.theme,
  },

  actions: {
    apply() {
      const root = document.documentElement
      root.setAttribute('data-theme', this.resolvedTheme)
      root.setAttribute('data-palette', this.palette)
      root.setAttribute('lang', this.locale)
    },

    setTheme(theme) {
      this.theme = theme
      localStorage.setItem(THEME_KEY, theme)
      this.apply()
    },

    /** Cycle light → dark → follow the OS. */
    cycleTheme() {
      const order = ['light', 'dark', 'system']
      const next = order[(order.indexOf(this.theme) + 1) % order.length]
      this.setTheme(next)
      return next
    },

    /** Collapse the rail to icons only. Remembered per device. */
    toggleRail() {
      this.railCollapsed = !this.railCollapsed
      localStorage.setItem(RAIL_KEY, this.railCollapsed ? '1' : '0')
    },

    setPalette(palette) {
      this.palette = palette
      localStorage.setItem(PALETTE_KEY, palette)
      this.apply()
    },

    setLocale(locale) {
      this.locale = locale
      localStorage.setItem(LOCALE_KEY, locale)
      this.apply()
    },

    /** Repaint when the OS flips and we're following it. */
    watchSystem() {
      if (!window.matchMedia) return
      window
        .matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => {
          if (this.theme === 'system') this.apply()
        })
    },
  },
})
