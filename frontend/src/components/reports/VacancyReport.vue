<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { reports } from '@/api/resources'
import { errorMessage } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatMoney } from '@/composables/format'
import ReportShell from './ReportShell.vue'
import StatStrip from './StatStrip.vue'
import StatusPill from '@/components/StatusPill.vue'
import EmptyState from '@/components/EmptyState.vue'

const { t } = useI18n()
const toast = useToastStore()
const data = ref(null)
const loading = ref(true)

const columns = [
  { key: 'property', label: 'Property' },
  { key: 'unit', label: 'Unit' },
  { key: 'status', label: 'Status' },
  { key: 'bedrooms', label: 'Bedrooms' },
  { key: 'monthly_rent', label: 'Monthly rent' },
  { key: 'vacant_since', label: 'Vacant since' },
  { key: 'days_vacant', label: 'Days vacant' },
  { key: 'revenue_lost', label: 'Revenue lost' },
  { key: 'last_tenant', label: 'Last tenant' },
]

const stats = computed(() => {
  const totals = data.value?.totals
  if (!totals) return []
  return [
    { label: t('reports.vacancy.rate'), value: `${totals.vacancy_rate}%`,
      note: t('reports.vacancy.ofUnits', { vacant: totals.vacant_units, total: totals.total_units }) },
    { label: t('reports.vacancy.monthlyLoss'), value: formatMoney(totals.monthly_loss), tone: 'negative' },
    { label: t('reports.vacancy.lostToDate'), value: formatMoney(totals.revenue_lost_to_date), tone: 'negative' },
    { label: t('reports.vacancy.averageDays'), value: totals.average_days_vacant,
      note: t('reports.vacancy.longest', { days: totals.longest_vacant_days }) },
  ]
})

async function load() {
  loading.value = true
  try {
    const response = await reports.vacancy()
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
    :title="t('reports.vacancy.title')"
    :lede="t('reports.vacancy.lede')"
    :loading="loading"
    :generated-at="data?.generated_at"
    filename="vacancy"
    :columns="columns"
    :rows="data?.rows ?? []"
  >
    <template v-if="data">
      <StatStrip :stats="stats" />

      <div class="table-scroll" v-if="data.rows.length">
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('leases.unit') }}</th>
              <th>{{ t('units.property') }}</th>
              <th>{{ t('common.status') }}</th>
              <th class="right">{{ t('reports.vacancy.since') }}</th>
              <th class="right">{{ t('reports.vacancy.days') }}</th>
              <th class="right">{{ t('units.monthlyRent') }}</th>
              <th class="right">{{ t('reports.vacancy.lost') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in data.rows" :key="row.unit_id">
              <td class="strong">{{ row.unit }}</td>
              <td class="muted">{{ row.property }}</td>
              <td><StatusPill :status="row.status" /></td>
              <td class="right num muted">{{ formatDate(row.vacant_since) }}</td>
              <td class="right num" :class="{ bad: row.days_vacant > 60 }">{{ row.days_vacant }}</td>
              <td class="right num">{{ formatMoney(row.monthly_rent) }}</td>
              <td class="right num bad">{{ formatMoney(row.revenue_lost) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <EmptyState v-else :title="t('reports.vacancy.empty')" :hint="t('reports.vacancy.emptyHint')" />
    </template>
  </ReportShell>
</template>

<style scoped>
.strong { font-weight: 600; }
.bad { color: var(--negative); font-weight: 500; }
</style>
