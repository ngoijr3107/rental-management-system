<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { reports } from '@/api/resources'
import { errorMessage } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import { formatMoney } from '@/composables/format'
import ReportShell from './ReportShell.vue'
import StatStrip from './StatStrip.vue'
import EmptyState from '@/components/EmptyState.vue'

const { t } = useI18n()
const toast = useToastStore()
const data = ref(null)
const loading = ref(true)
const range = ref({ from: '', to: '' })

const columns = [
  { key: 'property', label: 'Property' },
  { key: 'units', label: 'Units' },
  { key: 'occupied', label: 'Occupied' },
  { key: 'occupancy_rate', label: 'Occupancy %' },
  { key: 'contracted_monthly', label: 'Contracted monthly' },
  { key: 'billed', label: 'Billed' },
  { key: 'collected', label: 'Collected' },
  { key: 'uncollected', label: 'Uncollected' },
  { key: 'maintenance_cost', label: 'Maintenance' },
  { key: 'net', label: 'Net' },
  { key: 'collection_rate', label: 'Collection rate %' },
  { key: 'maintenance_ratio', label: 'Maintenance % of income' },
]

const stats = computed(() => {
  const totals = data.value?.totals
  if (!totals) return []
  return [
    { label: t('reports.performance.collected'), value: formatMoney(totals.collected), tone: 'money' },
    { label: t('reports.performance.maintenance'), value: formatMoney(totals.maintenance_cost), tone: 'warning' },
    { label: t('reports.performance.net'), value: formatMoney(totals.net), tone: 'positive' },
    { label: t('reports.performance.best'), value: totals.best ?? '—',
      note: totals.weakest ? t('reports.performance.weakest', { name: totals.weakest }) : '' },
  ]
})

const peak = computed(() => Math.max(...(data.value?.rows ?? []).map((r) => r.collected), 1))

async function load() {
  loading.value = true
  try {
    const response = await reports.propertyPerformance({ from: range.value.from, to: range.value.to })
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
    :title="t('reports.performance.title')"
    :lede="t('reports.performance.lede')"
    :loading="loading"
    :generated-at="data?.generated_at"
    filename="property-performance"
    :columns="columns"
    :rows="data?.rows ?? []"
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

      <div class="table-scroll" v-if="data.rows.length">
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('units.property') }}</th>
              <th class="right">{{ t('reports.performance.occupancy') }}</th>
              <th class="right">{{ t('reports.collections.billed') }}</th>
              <th class="right">{{ t('reports.collections.collected') }}</th>
              <th class="right">{{ t('reports.performance.maintenance') }}</th>
              <th class="right">{{ t('reports.performance.net') }}</th>
              <th class="share">{{ t('reports.performance.share') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in data.rows" :key="row.property_id">
              <td class="strong">{{ row.property }}</td>
              <td class="right num muted">{{ row.occupancy_rate }}% ({{ row.occupied }}/{{ row.units }})</td>
              <td class="right num">{{ formatMoney(row.billed) }}</td>
              <td class="right num paid">{{ formatMoney(row.collected) }}</td>
              <td class="right num warn">{{ formatMoney(row.maintenance_cost) }}</td>
              <td class="right num net">{{ formatMoney(row.net) }}</td>
              <td class="share">
                <span class="bar"><i :style="{ width: `${(row.collected / peak) * 100}%` }" /></span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <EmptyState v-else :title="t('reports.performance.empty')" :hint="t('reports.performance.emptyHint')" />
    </template>
  </ReportShell>
</template>

<style scoped>
.strong { font-weight: 600; }
.paid { color: var(--positive); }
.warn { color: var(--warning); }
.net { font-weight: 600; color: var(--money); }
.share { width: 120px; }
.bar { display: block; height: 8px; border-radius: 3px; background: var(--surface-3); overflow: hidden; }
.bar i { display: block; height: 100%; background: var(--money); border-radius: 3px; }
</style>
