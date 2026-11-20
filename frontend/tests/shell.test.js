import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHashHistory } from 'vue-router'
import i18n from '@/i18n'
import AppShell from '@/components/AppShell.vue'
import { useAuthStore } from '@/stores/auth'
import { usePrefsStore } from '@/stores/prefs'

const router = createRouter({
  history: createWebHashHistory(),
  routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div/>' } }],
})

async function shell(user) {
  setActivePinia(createPinia())
  const auth = useAuthStore()
  auth.user = user
  auth.settings = { business_name: 'Home Africa', currency: 'USD', logo_url: null }
  router.push('/reports/collections')
  await router.isReady()
  return mount(AppShell, { global: { plugins: [router, i18n] } })
}

const OWNER = { id: 1, name: 'A', email: 'a@b.c', role: 'owner', is_owner: true, permissions: ['*'] }
const AGENT = { id: 2, name: 'B', email: 'b@b.c', role: 'agent', is_owner: false,
  permissions: ['tenants.manage', 'leases.manage', 'invoices.manage', 'payments.record', 'maintenance.manage', 'reports.view'] }

describe('sidebar', () => {
  it('renders every nav link for an owner', async () => {
    const w = await shell(OWNER)
    const links = w.findAll('.rm-link')
    console.log('owner nav links:', links.map((l) => l.text()).join(' | '))
    expect(links.length).toBe(12)
  })

  it('hides admin-only links from an agent but keeps the rest', async () => {
    const w = await shell(AGENT)
    const links = w.findAll('.rm-link').map((l) => l.text())
    console.log('agent nav links:', links.join(' | '))
    expect(links).not.toContain('Team')
    expect(links).toContain('Settings')
    expect(links.length).toBe(10)
  })

  it('still renders the account block and the brand', async () => {
    const w = await shell(OWNER)
    expect(w.find('.rm-account').exists()).toBe(true)
    expect(w.find('.rm-brand').exists()).toBe(true)
    console.log('brand html:', w.find('.rm-brand').html().replace(/\s+/g, ' '))
  })

  it('falls back to the open links when permission data is missing', async () => {
    const w = await shell({ id: 3, name: 'C', email: 'c@b.c' }) // no permissions array
    const links = w.findAll('.rm-link').map((l) => l.text())
    console.log('no-permissions nav links:', links.length, links.join(' | '))
    expect(links.length).toBeGreaterThanOrEqual(9)
    expect(links).toContain('Dashboard')
  })

  it('renders an icon beside every nav link', async () => {
    const w = await shell(OWNER)
    const links = w.findAll('.rm-link')
    const withIcon = links.filter((l) => l.find('svg').exists())
    console.log('links with icons:', withIcon.length, 'of', links.length)
    expect(withIcon.length).toBe(links.length)
  })

  it('collapses to an icon rail without losing any link', async () => {
    const w = await shell(OWNER)
    const prefs = usePrefsStore()
    const before = w.findAll('.rm-link').length

    prefs.toggleRail()
    await w.vm.$nextTick()

    expect(w.find('.rm-shell').classes()).toContain('is-collapsed')
    expect(w.findAll('.rm-link').length).toBe(before)
    // Labels remain in the DOM as tooltips and for screen readers.
    expect(w.findAll('.rm-link')[0].attributes('title')).toBe('Dashboard')

    prefs.toggleRail()
    await w.vm.$nextTick()
    expect(w.find('.rm-shell').classes()).not.toContain('is-collapsed')
  })

  it('never renders an empty rail, even with no user at all', async () => {
    const w = await shell(null)
    const links = w.findAll('.rm-link').map((l) => l.text())
    console.log('no-user nav links:', links.length)
    expect(links.length).toBeGreaterThan(0)
    expect(w.find('.rm-account').exists()).toBe(true)
  })
})
