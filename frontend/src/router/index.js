import { createRouter, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { guest: true } },
  { path: '/register', name: 'register', component: () => import('@/views/RegisterView.vue'), meta: { guest: true } },
  { path: '/', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },
  { path: '/properties', name: 'properties', component: () => import('@/views/PropertiesView.vue') },
  { path: '/properties/:id', name: 'property', component: () => import('@/views/PropertyDetailView.vue'), props: true },
  { path: '/units', name: 'units', component: () => import('@/views/UnitsView.vue') },
  { path: '/tenants', name: 'tenants', component: () => import('@/views/TenantsView.vue') },
  { path: '/leases', name: 'leases', component: () => import('@/views/LeasesView.vue') },
  { path: '/leases/:id', name: 'lease', component: () => import('@/views/LeaseDetailView.vue'), props: true },
  { path: '/invoices', name: 'invoices', component: () => import('@/views/InvoicesView.vue') },
  { path: '/invoices/:id', name: 'invoice', component: () => import('@/views/InvoiceDetailView.vue'), props: true },
  { path: '/payments', name: 'payments', component: () => import('@/views/PaymentsView.vue') },
  { path: '/maintenance', name: 'maintenance', component: () => import('@/views/MaintenanceView.vue') },
  { path: '/team', name: 'team', component: () => import('@/views/TeamView.vue'), meta: { requires: 'users.manage' } },
  { path: '/settings', name: 'settings', component: () => import('@/views/SettingsView.vue') },
  { path: '/profile', name: 'profile', component: () => import('@/views/ProfileView.vue') },
  { path: '/activity', name: 'activity', component: () => import('@/views/ActivityView.vue'), meta: { requires: 'activity.view' } },
  { path: '/reports', redirect: '/reports/rent-roll' },
  {
    path: '/reports/:section',
    name: 'reports',
    component: () => import('@/views/ReportsView.vue'),
    props: true,
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const store = useAuthStore()
  await store.restore()

  if (!to.meta.guest && !store.isAuthenticated) {
    return { name: 'login', query: { next: to.fullPath } }
  }
  if (to.meta.guest && store.isAuthenticated) {
    return { name: 'dashboard' }
  }

  // A route the role can't use sends them home rather than showing a 403 screen.
  if (to.meta.requires && !store.can(to.meta.requires)) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
