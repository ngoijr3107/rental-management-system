import { defineStore } from 'pinia'
import { auth, settings as settingsApi } from '@/api/resources'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('rm_token') || '',
    user: null,
    settings: null,
    ready: false,
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.token),
    isOwner: (state) => Boolean(state.user?.is_owner),
    role: (state) => state.user?.role ?? null,

    /**
     * Reading is open to every member, so this only gates actions. The API
     * enforces the same rules — this just stops the UI offering a button
     * that would come back 403.
     */
    can: (state) => (ability) => {
      const list = state.user?.permissions ?? []
      return list.includes('*') || list.includes(ability)
    },

    currency: (state) => state.settings?.currency || 'USD',

    /** The account's own name, or null to fall back to the product name. */
    brandName: (state) => {
      const name = state.settings?.business_name?.trim()
      return name || null
    },

    logoUrl: (state) => state.settings?.logo_url || null,

    /** Initials for the fallback mark when no logo is set. */
    brandInitials: (state) => {
      const name = state.settings?.business_name?.trim()
      if (!name) return 'RM'
      return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase()
    },
  },

  actions: {
    setToken(token) {
      this.token = token
      if (token) localStorage.setItem('rm_token', token)
      else localStorage.removeItem('rm_token')
    },

    async login(credentials) {
      const { data } = await auth.login(credentials)
      this.setToken(data.token)
      this.user = data.user
      await this.loadSettings()
      this.ready = true
    },

    async register(payload) {
      const { data } = await auth.register(payload)
      this.setToken(data.token)
      this.user = data.user
      await this.loadSettings()
      this.ready = true
    },

    async loadSettings() {
      try {
        const { data } = await settingsApi.show()
        this.settings = data.data
      } catch {
        this.settings = null
      }
    },

    /** Restore the signed-in user on a hard refresh. */
    async restore() {
      if (!this.token || this.user) {
        this.ready = true
        return
      }
      try {
        const { data } = await auth.me()
        this.user = data.data
        await this.loadSettings()
      } catch {
        this.setToken('')
      } finally {
        this.ready = true
      }
    },

    async logout() {
      try {
        await auth.logout()
      } catch {
        // The token is being discarded either way.
      }
      this.setToken('')
      this.user = null
      this.settings = null
    },
  },
})
