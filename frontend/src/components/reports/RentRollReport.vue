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

const { t } = useI18n()
const toast = useToastStore()
const data = ref(null)
const loading = ref(true)

const flatRows = computed(() =>
  (data.value?.groups ?? []).flatMap((group) =>
    group.units.map((unit) => ({ ...unit, property: group.property })),
  ),
)

const columns = [
  { key: 'property', label: 'Property' },
  { key: 'unit', label: 'Unit' },
  { key: 'status', label: 'Status' },
  { key: 'tenant', label: 'Tenant' },
  { key: 'phone', label: 'Phone' },
  { key: 'market_rent', label: 'Market rent' },
  { key: 'contracted_rent', label: 'Contracted rent' },
  { key: 'deposit_held', label: 'Deposit held' },
  { key: 'balance', label: 'Balance owed' },
  { key: 'lease_start', label: 'Lease start' },
  { key: 'lease_end', label: 'Lease end' },
]

const stats = computed(() => {
  const totals = data.value?.totals
  if (!totals) return []
  return [
    { label: t('reports.rentRoll.contracted'), value: formatMoney(totals.contracted_monthly), tone: 'money' },
    { label: t('reports.rentRoll.vacancyLoss'), value: formatMoney(totals.vacancy_loss_monthly), tone: 'negative' },
    { label: t('reports.rentRoll.potential'), value: formatMoney(totals.potential_monthly) },
    {
      label: t('reports.rentRoll.occupancy'),
      value: `${totals.occupancy_rate}%`,
      note: t('reports.rentRoll.occupiedOf', { occupied: totals.occupied, total: totals.units }),
    },
    { label: t('reports.rentRoll.arrears'), value: formatMoney(totals.arrears_total), tone: 'negative' },
  ]
})

async function load() {
  loading.value = true
  try {
    const response = await reports.rentRoll()
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
    :title="t('reports.rentRoll.title')"
    :lede="t('reports.rentRoll.lede')"
    :loading="loading"
    :generated-at="data?.generated_at"
    filename="rent-roll"
    :columns="columns"
    :rows="flatRows"
  >
    <StatStrip v-if="data" :stats="stats" />

    <div v-if="data" class="groups">
      <div v-for="group in data.groups" :key="group.property_id" class="group">
        <div class="group-head">
          <h4>{{ group.property }}</h4>
          <span class="num muted">{{ formatMoney(group.contracted_rent) }}</span>
        </div>
        <div class="table-scroll">
          <table class="table">
          <thead>
            <tr>
              <th>{{ t('leases.unit') }}</th>
              <th>{{ t('common.status') }}</th>
              <th>{{ t('leases.tenant') }}</th>
              <th class="right">{{ t('reports.rentRoll.market') }}</th>
              <th class="right">{{ t('reports.rentRoll.contractedShort') }}</th>
              <th class="right">{{ t('leases.deposit') }}</th>
              <th class="right">{{ t('invoices.owed') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="unit in group.units" :key="unit.unit_id">
              <td class="strong">{{ unit.unit }}</td>
              <td><StatusPill :status="unit.status" /></td>
              <td>
                <RouterLink v-if="unit.lease_id" :to="`/leases/${unit.lease_id}`" class="strong-link">
                  {{ unit.tenant }}
                </RouterLink>
                <span v-else class="muted">—</span>
              </td>
              <td class="right num muted">{{ formatMoney(unit.market_rent) }}</td>
              <td class="right num">{{ unit.contracted_rent ? formatMoney(unit.contracted_rent) : '—' }}</td>
              <td class="right num muted">{{ unit.deposit_held ? formatMoney(unit.deposit_held) : '—' }}</td>
              <td class="right num" :class="{ owed: unit.balance > 0 }">
                {{ unit.balance > 0 ? formatMoney(unit.balance) : '—' }}
              </td>
            </tr>
          </tbody>
          </table>
        </div>
      </div>
    </div>
  </ReportShell>
</template>

<style scoped>
.group + .group { border-top: 1px solid var(--line); }
.group-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  padding: var(--sp-4) var(--sp-5) var(--sp-2);
}
.group-head h4 { font-family: var(--display); font-size: var(--text-md); }
.strong { font-weight: 600; }
.owed { color: var(--negative); font-weight: 500; }
</style>
