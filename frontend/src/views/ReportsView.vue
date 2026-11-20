<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import PageHeader from '@/components/PageHeader.vue'
import RentRollReport from '@/components/reports/RentRollReport.vue'
import ArrearsReport from '@/components/reports/ArrearsReport.vue'
import CollectionsReport from '@/components/reports/CollectionsReport.vue'
import VacancyReport from '@/components/reports/VacancyReport.vue'
import RenewalsReport from '@/components/reports/RenewalsReport.vue'
import PropertyPerformanceReport from '@/components/reports/PropertyPerformanceReport.vue'
import MaintenanceReport from '@/components/reports/MaintenanceReport.vue'
import TenantStatementReport from '@/components/reports/TenantStatementReport.vue'

const props = defineProps({ section: { type: String, default: 'rent-roll' } })

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const TABS = [
  { id: 'rent-roll', key: 'reports.rentRoll.tab', component: RentRollReport },
  { id: 'arrears', key: 'reports.arrears.tab', component: ArrearsReport },
  { id: 'collections', key: 'reports.collections.tab', component: CollectionsReport },
  { id: 'vacancy', key: 'reports.vacancy.tab', component: VacancyReport },
  { id: 'renewals', key: 'reports.renewals.tab', component: RenewalsReport },
  { id: 'performance', key: 'reports.performance.tab', component: PropertyPerformanceReport },
  { id: 'maintenance', key: 'reports.maintenance.tab', component: MaintenanceReport },
  { id: 'statement', key: 'reports.statement.tab', component: TenantStatementReport },
]

const statementTenant = ref(route.query.tenant ?? null)

const active = computed(() => TABS.find((tab) => tab.id === props.section) ?? TABS[0])

function go(id) {
  router.push(`/reports/${id}`)
}

/** Clicking a debtor in the arrears table opens their statement. */
function openStatement(tenantId) {
  statementTenant.value = tenantId
  router.push({ path: '/reports/statement', query: { tenant: tenantId } })
}

watch(() => route.query.tenant, (id) => { if (id) statementTenant.value = id })
</script>

<template>
  <div class="stack">
    <PageHeader :title="t('reports.title')" :eyebrow="t('nav.insight')" />

    <nav class="tabs no-print" role="tablist">
      <button
        v-for="tab in TABS"
        :key="tab.id"
        type="button"
        role="tab"
        class="tab"
        :class="{ on: tab.id === active.id }"
        :aria-selected="tab.id === active.id"
        @click="go(tab.id)"
      >
        {{ t(tab.key) }}
      </button>
    </nav>

    <component
      :is="active.component"
      :tenant-id="active.id === 'statement' ? statementTenant : null"
      @statement="openStatement"
    />
  </div>
</template>

<style scoped>
.tabs {
  display: flex;
  gap: 3px;
  padding: 5px;
  overflow-x: auto;
  scrollbar-width: none;
  border: 1px solid var(--line);
  border-radius: var(--radius-pill);
  background: var(--surface);
  box-shadow: var(--shadow-1);
}
.tab {
  flex: none;
  height: 32px;
  padding: 0 15px;
  border: 0;
  border-radius: var(--radius-pill);
  background: transparent;
  color: var(--text-muted);
  font-family: var(--body);
  font-size: var(--text-base);
  white-space: nowrap;
  cursor: pointer;
  transition: background 0.16s var(--ease), color 0.16s var(--ease);
}
.tab:hover { background: var(--surface-2); color: var(--text); }
.tab.on {
  background: var(--accent);
  color: var(--accent-contrast);
  font-weight: 500;
  box-shadow: var(--shadow-1);
}

.tabs::-webkit-scrollbar { display: none; }

@media (max-width: 620px) {
  .tabs { border-radius: var(--radius); }
  .tab { font-size: var(--text-sm); padding: 0 12px; }
}
</style>
