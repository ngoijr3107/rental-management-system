<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { reports } from '@/api/resources'
import { errorMessage } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import { formatMoney } from '@/composables/format'
import ReportShell from './ReportShell.vue'
import StatStrip from './StatStrip.vue'
import StatusPill from '@/components/StatusPill.vue'
import EmptyState from '@/components/EmptyState.vue'

const { t } = useI18n()
const toast = useToastStore()
const data = ref(null)
const loading = ref(true)
const range = ref({ from: '', to: '' })

const columns = [
  { key: 'property', label: 'Property' },
  { key: 'jobs', label: 'Jobs' },
  { key: 'open', label: 'Still open' },
  { key: 'cost', label: 'Cost' },
]

const stats = computed(() => {
  const totals = data.value?.totals
  if (!totals) return []
  return [
    { label: t('reports.maintenance.totalCost'), value: formatMoney(totals.cost), tone: 'warning' },
    { label: t('reports.maintenance.jobs'), value: totals.jobs,
      note: t('reports.maintenance.stillOpen', { count: totals.open }) },
    { label: t('reports.maintenance.averageCost'), value: formatMoney(totals.average_cost) },
    { label: t('reports.maintenance.averageDays'),
      value: totals.average_resolution_days === null ? '—' : totals.average_resolution_days },
  ]
})

async function load() {
  loading.value = true
  try {
    const response = await reports.maintenance({ from: range.value.from, to: range.value.to })
    data.value = response.data
  } catch (error) {
    toast.error(errorMessage(error, t('reports.loadFailed')))
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <ReportShell
    :title="t('reports.maintenance.title')"
    :lede="t('reports.maintenance.lede')"
    :loading="loading"
    :generated-at="data?.generated_at"
    filename="maintenance-spend"
    :columns="columns"
    :rows="data?.properties ?? []"
  >
    <template #filters>
      <div class="field">
        <label for="from">{{ t('reports.from') }}</label>
        <input id="from" v-model="range.from" class="input num" type="date" />
      </div>
      <div class="field">
        <label for="to">{{ t('reports.to') }}</label>
        <input id="to" v-model="range.to" class="input num" type="date" />
      </div>
      <button class="btn btn-sm" type="button" @click="load">{{ t('reports.apply') }}</button>
    </template>

    <template v-if="data">
      <StatStrip :stats="stats" />

      <template v-if="data.totals.jobs">
        <div class="split">
          <div class="panel">
            <p class="eyebrow">{{ t('reports.maintenance.byProperty') }}</p>
            <div class="table-scroll">
              <table class="table bare">
              <tbody>
                <tr v-for="row in data.properties" :key="row.property">
                  <td>{{ row.property }}</td>
                  <td class="right num muted">{{ row.jobs }}</td>
                  <td class="right num warn">{{ formatMoney(row.cost) }}</td>
                </tr>
              </tbody>
              </table>
            </div>
          </div>

          <div class="panel">
            <p class="eyebrow">{{ t('reports.maintenance.byPriority') }}</p>
            <div class="table-scroll">
              <table class="table bare">
              <tbody>
                <tr v-for="row in data.priorities" :key="row.priority">
                  <td><StatusPill :status="row.priority" /></td>
                  <td class="right num muted">{{ row.jobs }}</td>
                  <td class="right num warn">{{ formatMoney(row.cost) }}</td>
                </tr>
              </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="worst">
          <p class="eyebrow">{{ t('reports.maintenance.worstUnits') }}</p>
          <p class="muted hint">{{ t('reports.maintenance.worstHint') }}</p>
          <div class="table-scroll">
            <table class="table">
            <thead>
              <tr>
                <th>{{ t('leases.unit') }}</th>
                <th>{{ t('units.property') }}</th>
                <th class="right">{{ t('reports.maintenance.jobs') }}</th>
                <th class="right">{{ t('reports.maintenance.totalCost') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in data.worst_units" :key="row.unit_id">
                <td class="strong">{{ row.unit }}</td>
                <td class="muted">{{ row.property }}</td>
                <td class="right num">{{ row.jobs }}</td>
                <td class="right num warn">{{ formatMoney(row.cost) }}</td>
              </tr>
            </tbody>
            </table>
          </div>
        </div>
      </template>

      <EmptyState v-else :title="t('reports.maintenance.empty')" :hint="t('reports.maintenance.emptyHint')" />
    </template>
  </ReportShell>
</template>

<style scoped>
.split {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: var(--sp-5);
  padding: var(--sp-5);
  border-bottom: 1px solid var(--line);
}
.panel .eyebrow { margin: 0 0 var(--sp-2); }
.table.bare td { padding: 7px 0; }
.worst { padding: var(--sp-5) 0 0; }
.worst .eyebrow, .worst .hint { padding: 0 var(--sp-5); margin: 0; }
.worst .hint { font-size: var(--text-sm); margin: 3px 0 var(--sp-3); }
.strong { font-weight: 600; }
.warn { color: var(--warning); font-weight: 500; }
</style>
