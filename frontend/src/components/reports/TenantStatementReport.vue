<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { reports, tenants as tenantsApi } from '@/api/resources'
import { errorMessage } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatMoney } from '@/composables/format'
import ReportShell from './ReportShell.vue'
import EmptyState from '@/components/EmptyState.vue'

const props = defineProps({ tenantId: { type: [String, Number], default: null } })

const { t } = useI18n()
const toast = useToastStore()
const data = ref(null)
const loading = ref(false)
const options = ref([])
const selected = ref(props.tenantId ?? '')

const columns = [
  { key: 'date', label: 'Date' },
  { key: 'reference', label: 'Reference' },
  { key: 'description', label: 'Description' },
  { key: 'charge', label: 'Charge' },
  { key: 'credit', label: 'Payment' },
  { key: 'balance', label: 'Balance' },
]

const extra = computed(() =>
  data.value
    ? [
        [t('reports.statement.title'), data.value.tenant.name],
        [t('leases.unit'), `${data.value.lease?.unit ?? '—'} ${data.value.lease?.property ?? ''}`],
        [t('reports.statement.balanceDue'), data.value.totals.balance],
        [],
      ]
    : [],
)

async function loadTenants() {
  const { data: response } = await tenantsApi.list({ per_page: 100 })
  options.value = response.data ?? []
  if (!selected.value && options.value.length) selected.value = options.value[0].id
}

async function load() {
  if (!selected.value) return
  loading.value = true
  try {
    const response = await reports.tenantStatement(selected.value)
    data.value = response.data
  } catch (error) {
    toast.error(errorMessage(error, t('reports.loadFailed')))
  } finally {
    loading.value = false
  }
}

watch(selected, load)
watch(() => props.tenantId, (id) => { if (id) selected.value = id })

onMounted(async () => {
  await loadTenants()
  await load()
})
</script>

<template>
  <ReportShell
    :title="t('reports.statement.title')"
    :lede="t('reports.statement.lede')"
    :loading="loading"
    :generated-at="data?.generated_at"
    filename="tenant-statement"
    :columns="columns"
    :rows="data?.entries ?? []"
    :extra="extra"
  >
    <template #filters>
      <div class="field" style="min-width: 240px">
        <label for="tenant">{{ t('leases.tenant') }}</label>
        <select id="tenant" v-model="selected" class="select">
          <option v-for="tenant in options" :key="tenant.id" :value="tenant.id">
            {{ tenant.full_name }} · {{ tenant.phone }}
          </option>
        </select>
      </div>
    </template>

    <template v-if="data">
      <header class="letterhead">
        <div>
          <p class="eyebrow">{{ t('reports.statement.for') }}</p>
          <p class="name">{{ data.tenant.name }}</p>
          <p class="muted num contact">{{ data.tenant.phone }}<template v-if="data.tenant.email"> · {{ data.tenant.email }}</template></p>
        </div>
        <div v-if="data.lease">
          <p class="eyebrow">{{ t('leases.unit') }}</p>
          <p class="name">{{ data.lease.unit }}</p>
          <p class="muted contact">{{ data.lease.property }}</p>
        </div>
        <div>
          <p class="eyebrow">{{ t('reports.statement.balanceDue') }}</p>
          <p class="num balance" :class="{ owed: data.totals.balance > 0 }">
            {{ formatMoney(data.totals.balance, true) }}
          </p>
          <p class="muted contact">
            {{ t('reports.statement.chargedPaid', {
              charged: formatMoney(data.totals.charged),
              paid: formatMoney(data.totals.paid),
            }) }}
          </p>
        </div>
      </header>

      <div class="table-scroll" v-if="data.entries.length">
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('common.date') }}</th>
              <th>{{ t('payments.reference') }}</th>
              <th>{{ t('reports.statement.description') }}</th>
              <th class="right">{{ t('reports.statement.charge') }}</th>
              <th class="right">{{ t('reports.statement.payment') }}</th>
              <th class="right">{{ t('reports.statement.balance') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(entry, i) in data.entries" :key="`${entry.reference}-${i}`">
              <td class="num">{{ formatDate(entry.date) }}</td>
              <td class="num muted">{{ entry.reference }}</td>
              <td>{{ entry.type === 'payment' ? t(`status.${entry.description}`) : entry.description }}</td>
              <td class="right num">{{ entry.charge ? formatMoney(entry.charge, true) : '—' }}</td>
              <td class="right num paid">{{ entry.credit ? formatMoney(entry.credit, true) : '—' }}</td>
              <td class="right num balance-cell">{{ formatMoney(entry.balance, true) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <EmptyState v-else :title="t('reports.statement.empty')" :hint="t('reports.statement.emptyHint')" />
    </template>
  </ReportShell>
</template>

<style scoped>
.letterhead {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: var(--sp-5);
  padding: var(--sp-5);
  border-bottom: 1px solid var(--line);
}
.name { margin: 5px 0 0; font-family: var(--display); font-size: var(--text-lg); font-weight: 600; }
.contact { margin: 2px 0 0; font-size: var(--text-sm); }
.balance { margin: 5px 0 0; font-size: var(--text-xl); font-weight: 600; }
.balance.owed { color: var(--negative); }
.balance-cell { font-weight: 600; }
.paid { color: var(--positive); }
</style>
