<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { reports } from '@/api/resources'
import { errorMessage } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatMoney } from '@/composables/format'
import ReportShell from './ReportShell.vue'
import StatStrip from './StatStrip.vue'
import EmptyState from '@/components/EmptyState.vue'

const emit = defineEmits(['statement'])

const { t } = useI18n()
const toast = useToastStore()
const data = ref(null)
const loading = ref(true)

const BUCKETS = ['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus']

const columns = [
  { key: 'tenant', label: 'Tenant' },
  { key: 'phone', label: 'Phone' },
  { key: 'property', label: 'Property' },
  { key: 'unit', label: 'Unit' },
  { key: 'invoices', label: 'Unpaid invoices' },
  { key: 'oldest_due', label: 'Oldest due' },
  { key: 'days_late', label: 'Days late' },
  { key: 'current', label: 'Not yet due' },
  { key: 'd1_30', label: '1-30 days' },
  { key: 'd31_60', label: '31-60 days' },
  { key: 'd61_90', label: '61-90 days' },
  { key: 'd90_plus', label: '90+ days' },
  { key: 'total', label: 'Total owed' },
]

const stats = computed(() => {
  if (!data.value) return []
  const { buckets, totals } = data.value
  return [
    { label: t('reports.arrears.totalOwed'), value: formatMoney(totals.total), tone: 'negative' },
    { label: t('reports.arrears.overdueOnly'), value: formatMoney(totals.overdue_total), tone: 'negative' },
    { label: t('reports.arrears.debtors'), value: totals.debtors },
    { label: t('reports.arrears.over90'), value: formatMoney(buckets.d90_plus), tone: 'negative' },
  ]
})

/** Widest bucket sets the bar scale. */
const scale = computed(() => {
  if (!data.value) return 1
  return Math.max(...BUCKETS.map((b) => data.value.buckets[b]), 1)
})

async function load() {
  loading.value = true
  try {
    const response = await reports.arrears()
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
    :title="t('reports.arrears.title')"
    :lede="t('reports.arrears.lede')"
    :loading="loading"
    :generated-at="data?.generated_at"
    filename="arrears-aging"
    :columns="columns"
    :rows="data?.rows ?? []"
  >
    <template v-if="data">
      <StatStrip :stats="stats" />

      <!-- Aging profile: how stale the debt is at a glance -->
      <div class="aging">
        <div v-for="bucket in BUCKETS" :key="bucket" class="band">
          <p class="eyebrow">{{ t(`reports.arrears.bucket.${bucket}`) }}</p>
          <div class="bar">
            <span
              :class="bucket"
              :style="{ width: `${(data.buckets[bucket] / scale) * 100}%` }"
            />
          </div>
          <p class="num amount">{{ formatMoney(data.buckets[bucket]) }}</p>
        </div>
      </div>

      <div class="table-scroll" v-if="data.rows.length">
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('leases.tenant') }}</th>
              <th>{{ t('leases.unit') }}</th>
              <th class="right">{{ t('reports.arrears.oldest') }}</th>
              <th class="right">{{ t('reports.arrears.daysLate') }}</th>
              <th class="right">{{ t('reports.arrears.bucket.d1_30') }}</th>
              <th class="right">{{ t('reports.arrears.bucket.d31_60') }}</th>
              <th class="right">{{ t('reports.arrears.bucket.d61_90') }}</th>
              <th class="right">{{ t('reports.arrears.bucket.d90_plus') }}</th>
              <th class="right">{{ t('reports.arrears.totalOwed') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in data.rows" :key="row.tenant_id ?? row.tenant">
              <td>
                <button class="linkish" type="button" @click="emit('statement', row.tenant_id)">
                  {{ row.tenant }}
                </button>
                <span class="muted phone num"> · {{ row.phone }}</span>
              </td>
              <td class="muted">{{ row.unit }} · {{ row.property }}</td>
              <td class="right num">{{ formatDate(row.oldest_due) }}</td>
              <td class="right num" :class="{ bad: row.days_late > 60 }">{{ row.days_late }}</td>
              <td class="right num muted">{{ row.d1_30 ? formatMoney(row.d1_30) : '—' }}</td>
              <td class="right num muted">{{ row.d31_60 ? formatMoney(row.d31_60) : '—' }}</td>
              <td class="right num muted">{{ row.d61_90 ? formatMoney(row.d61_90) : '—' }}</td>
              <td class="right num bad">{{ row.d90_plus ? formatMoney(row.d90_plus) : '—' }}</td>
              <td class="right num total">{{ formatMoney(row.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <EmptyState
        v-else
        :title="t('reports.arrears.empty')"
        :hint="t('reports.arrears.emptyHint')"
      />
    </template>
  </ReportShell>
</template>

<style scoped>
.aging {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
  padding: var(--sp-5);
  border-bottom: 1px solid var(--line);
}
.band { display: grid; grid-template-columns: 110px 1fr 120px; align-items: center; gap: var(--sp-3); }
.band .eyebrow { margin: 0; }
.bar { height: 12px; border-radius: 3px; background: var(--surface-3); overflow: hidden; }
.bar span { display: block; height: 100%; border-radius: 3px; transition: width 0.5s var(--ease); }
.bar span.current { background: var(--neutral); }
.bar span.d1_30 { background: var(--warning); }
.bar span.d31_60 { background: var(--warning); opacity: 0.85; }
.bar span.d61_90 { background: var(--negative); opacity: 0.8; }
.bar span.d90_plus { background: var(--negative); }
.amount { margin: 0; text-align: right; font-size: var(--text-sm); }
.linkish {
  border: 0;
  background: none;
  padding: 0;
  font-family: var(--body);
  font-size: var(--text-base);
  font-weight: 500;
  color: var(--accent-ink);
  cursor: pointer;
}
.linkish:hover { text-decoration: underline; }
.phone { font-size: var(--text-sm); }
.bad { color: var(--negative); }
.total { font-weight: 600; }
</style>
