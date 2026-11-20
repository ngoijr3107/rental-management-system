<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { reports } from '@/api/resources'
import { errorMessage } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatMoney } from '@/composables/format'
import ReportShell from './ReportShell.vue'
import StatStrip from './StatStrip.vue'
import EmptyState from '@/components/EmptyState.vue'

const { t } = useI18n()
const toast = useToastStore()
const data = ref(null)
const loading = ref(true)
const days = ref(90)

const columns = [
  { key: 'tenant', label: 'Tenant' },
  { key: 'phone', label: 'Phone' },
  { key: 'property', label: 'Property' },
  { key: 'unit', label: 'Unit' },
  { key: 'end_date', label: 'Lease ends' },
  { key: 'days_left', label: 'Days left' },
  { key: 'monthly_rent', label: 'Monthly rent' },
  { key: 'months_tenanted', label: 'Months tenanted' },
]

const stats = computed(() => {
  if (!data.value) return []
  const { totals, buckets } = data.value
  return [
    { label: t('reports.renewals.expiring'), value: totals.expiring, tone: 'warning' },
    { label: t('reports.renewals.rentAtRisk'), value: formatMoney(totals.monthly_rent_at_risk), tone: 'negative' },
    { label: t('reports.renewals.next30'), value: buckets.d0_30 + buckets.expired, tone: 'negative' },
    { label: t('reports.renewals.openEnded'), value: totals.open_ended,
      note: t('reports.renewals.openEndedNote') },
  ]
})

function urgency(daysLeft) {
  if (daysLeft < 0) return 'past'
  if (daysLeft <= 30) return 'soon'
  if (daysLeft <= 60) return 'watch'
  return 'later'
}

async function load() {
  loading.value = true
  try {
    const response = await reports.renewals({ days: days.value })
    data.value = response.data
  } catch (error) {
    toast.error(errorMessage(error, t('reports.loadFailed')))
  } finally {
    loading.value = false
  }
}

watch(days, load)
onMounted(load)
</script>

<template>
  <ReportShell
    :title="t('reports.renewals.title')"
    :lede="t('reports.renewals.lede')"
    :loading="loading"
    :generated-at="data?.generated_at"
    filename="renewals"
    :columns="columns"
    :rows="data?.rows ?? []"
  >
    <template #filters>
      <div class="field">
        <label for="days">{{ t('reports.renewals.window') }}</label>
        <select id="days" v-model.number="days" class="select">
          <option :value="30">30</option>
          <option :value="60">60</option>
          <option :value="90">90</option>
          <option :value="180">180</option>
        </select>
      </div>
    </template>

    <template v-if="data">
      <StatStrip :stats="stats" />

      <div class="table-scroll" v-if="data.rows.length">
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('leases.tenant') }}</th>
              <th>{{ t('leases.unit') }}</th>
              <th class="right">{{ t('reports.renewals.ends') }}</th>
              <th class="right">{{ t('reports.renewals.daysLeft') }}</th>
              <th class="right">{{ t('leases.rent') }}</th>
              <th class="right">{{ t('reports.renewals.tenanted') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in data.rows" :key="row.lease_id">
              <td>
                <RouterLink :to="`/leases/${row.lease_id}`" class="strong-link">{{ row.tenant }}</RouterLink>
                <span class="muted phone num"> · {{ row.phone }}</span>
              </td>
              <td class="muted">{{ row.unit }} · {{ row.property }}</td>
              <td class="right num">{{ formatDate(row.end_date) }}</td>
              <td class="right num" :class="urgency(row.days_left)">
                {{ row.days_left < 0 ? t('reports.renewals.overdue') : row.days_left }}
              </td>
              <td class="right num">{{ formatMoney(row.monthly_rent) }}</td>
              <td class="right num muted">{{ row.months_tenanted }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <EmptyState v-else :title="t('reports.renewals.empty')" :hint="t('reports.renewals.emptyHint')" />
    </template>
  </ReportShell>
</template>

<style scoped>
.phone { font-size: var(--text-sm); }
.past, .soon { color: var(--negative); font-weight: 600; }
.watch { color: var(--warning); font-weight: 500; }
.later { color: var(--text-muted); }
</style>
