<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { dashboard, invoices, units as unitsApi } from '@/api/resources'
import { errorMessage } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import { useCountUp } from '@/composables/useCountUp'
import { currentMonth, formatDate, formatMoney, formatMonth } from '@/composables/format'
import OccupancyBoard from '@/components/OccupancyBoard.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusPill from '@/components/StatusPill.vue'
import EmptyState from '@/components/EmptyState.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'
import AppIcon from '@/components/AppIcon.vue'

const router = useRouter()
const toast = useToastStore()
const { t } = useI18n()

const summary = ref(null)
const units = ref([])
const loading = ref(true)
const billing = ref(false)

const thisMonth = computed(() => formatMonth(`${currentMonth()}-01`))

const collected = computed(() => Number(summary.value?.finance?.collected_this_month ?? 0))
const collectedDisplay = useCountUp(collected)

const arrearsUnitIds = computed(() =>
  (summary.value?.overdue_invoices ?? [])
    .map((invoice) => invoice.lease?.unit?.id)
    .filter(Boolean),
)

const collectionRate = computed(() => {
  const billed = Number(summary.value?.finance?.billed_this_month ?? 0)
  if (billed <= 0) return null
  return Math.round((collected.value / billed) * 100)
})

async function load() {
  loading.value = true
  try {
    const [summaryResponse, unitsResponse] = await Promise.all([
      dashboard.load(),
      unitsApi.list({ per_page: 100 }),
    ])
    summary.value = summaryResponse.data
    units.value = unitsResponse.data.data ?? []
  } catch (error) {
    toast.error(errorMessage(error, t('dashboard.loadFailed')))
  } finally {
    loading.value = false
  }
}

async function generateInvoices() {
  billing.value = true
  try {
    const { data } = await invoices.generate({ month: currentMonth() })
    toast.success(
      data.count
        ? t('invoices.generated', { count: data.count, month: thisMonth.value })
        : t('invoices.alreadyBilled', { month: thisMonth.value }),
    )
    await load()
  } catch (error) {
    toast.error(errorMessage(error, t('invoices.generateFailed')))
  } finally {
    billing.value = false
  }
}

function openUnit(unit) {
  if (unit.active_lease) router.push(`/leases/${unit.active_lease.id}`)
  else router.push({ path: '/units', query: { property_id: unit.property_id } })
}

onMounted(load)
</script>

<template>
  <div class="stack">
    <PageHeader :title="t('dashboard.title')" :eyebrow="thisMonth">
      <template #actions>
        <button class="btn btn-ghost" type="button" @click="load">
          <AppIcon name="refresh" :size="15" />{{ t('common.refresh') }}
        </button>
        <button class="btn" type="button" :disabled="billing" @click="generateInvoices">
          <AppIcon name="invoices" :size="15" />
          {{ billing ? t('dashboard.raising') : t('dashboard.raiseRent') }}
        </button>
      </template>
    </PageHeader>

    <template v-if="loading">
      <section class="card"><SkeletonTable :rows="4" :columns="6" /></section>
      <section class="rail">
        <article v-for="n in 4" :key="n" class="card metric">
          <span class="skeleton sk-eyebrow" />
          <span class="skeleton sk-figure" />
          <span class="skeleton sk-note" />
        </article>
      </section>
    </template>

    <template v-else-if="summary">
      <!-- The board: the whole portfolio at a glance. -->
      <section class="card board-card">
        <div class="card-head">
          <div>
            <h3>{{ t('dashboard.board') }}</h3>
            <p class="muted sub">
              {{
                t('dashboard.boardSummary', {
                  occupied: summary.portfolio.occupied_units,
                  total: summary.portfolio.units,
                  rate: summary.portfolio.occupancy_rate,
                })
              }}
            </p>
          </div>
          <RouterLink to="/units" class="strong-link">{{ t('dashboard.allUnits') }}</RouterLink>
        </div>
        <div class="board-body">
          <OccupancyBoard
            v-if="units.length"
            :units="units"
            :arrears-unit-ids="arrearsUnitIds"
            @select="openUnit"
          />
          <EmptyState v-else :title="t('dashboard.noUnits')" :hint="t('dashboard.noUnitsHint')">
            <RouterLink to="/properties" class="btn">{{ t('dashboard.addProperty') }}</RouterLink>
          </EmptyState>
        </div>
      </section>

      <!-- Money rail -->
      <section class="rail">
        <article class="card metric lead">
          <p class="eyebrow">{{ t('dashboard.collectedIn', { month: thisMonth }) }}</p>
          <p class="num figure">{{ formatMoney(collectedDisplay) }}</p>
          <p class="muted note">
            {{ t('dashboard.ofBilled', { amount: formatMoney(summary.finance.billed_this_month) }) }}
            <template v-if="collectionRate !== null">
              · {{ t('dashboard.percentIn', { rate: collectionRate }) }}
            </template>
          </p>
          <div v-if="collectionRate !== null" class="meter">
            <span :style="{ width: `${Math.min(collectionRate, 100)}%` }" />
          </div>
        </article>

        <article class="card metric">
          <p class="eyebrow">{{ t('dashboard.outstanding') }}</p>
          <p class="num figure small">{{ formatMoney(summary.finance.outstanding_total) }}</p>
          <p class="muted note">{{ t('dashboard.outstandingNote') }}</p>
        </article>

        <article class="card metric" :class="{ alarm: summary.finance.overdue_invoices > 0 }">
          <p class="eyebrow">{{ t('dashboard.overdue') }}</p>
          <p class="num figure small">{{ formatMoney(summary.finance.overdue_total) }}</p>
          <p class="muted note">
            {{ t('dashboard.overdueNote', summary.finance.overdue_invoices) }}
          </p>
        </article>

        <article class="card metric">
          <p class="eyebrow">{{ t('dashboard.tenants') }}</p>
          <p class="num figure small">{{ summary.portfolio.tenants }}</p>
          <p class="muted note">
            {{
              t('dashboard.tenantsNote', {
                leases: summary.portfolio.active_leases,
                vacant: summary.portfolio.vacant_units,
              })
            }}
          </p>
        </article>
      </section>

      <div class="grid-2">
        <section class="card">
          <div class="card-head">
            <h3>{{ t('dashboard.needsChasing') }}</h3>
            <RouterLink to="/invoices?overdue=1" class="strong-link">
              {{ t('dashboard.allOverdue') }}
            </RouterLink>
          </div>
          <div class="table-scroll" v-if="summary.overdue_invoices.length">
            <table class="table">
              <thead>
                <tr>
                  <th>{{ t('leases.tenant') }}</th>
                  <th>{{ t('dashboard.due') }}</th>
                  <th class="right">{{ t('dashboard.owed') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="invoice in summary.overdue_invoices"
                  :key="invoice.id"
                  class="row-link"
                  @click="router.push(`/invoices/${invoice.id}`)"
                >
                  <td>
                    <span class="strong-link">{{ invoice.lease?.tenant?.full_name ?? '—' }}</span>
                    <span class="muted unit"> · {{ invoice.lease?.unit?.label ?? '' }}</span>
                  </td>
                  <td class="num">{{ formatDate(invoice.due_date) }}</td>
                  <td class="right num owed">{{ formatMoney(invoice.balance) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <EmptyState
            v-else
            :title="t('dashboard.nothingOverdue')"
            :hint="t('dashboard.nothingOverdueHint')"
          />
        </section>

        <section class="card">
          <div class="card-head">
            <h3>{{ t('dashboard.latestPayments') }}</h3>
            <RouterLink to="/payments" class="strong-link">
              {{ t('dashboard.allPayments') }}
            </RouterLink>
          </div>
          <div class="table-scroll" v-if="summary.recent_payments.length">
            <table class="table">
              <thead>
                <tr>
                  <th>{{ t('leases.tenant') }}</th>
                  <th>{{ t('dashboard.received') }}</th>
                  <th class="right">{{ t('common.amount') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="payment in summary.recent_payments" :key="payment.id">
                  <td>
                    {{ payment.invoice?.lease?.tenant?.full_name ?? '—' }}
                    <StatusPill :status="payment.method" tone="neutral" />
                  </td>
                  <td class="num">{{ formatDate(payment.paid_at) }}</td>
                  <td class="right num paid">{{ formatMoney(payment.amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <EmptyState
            v-else
            :title="t('dashboard.noPayments')"
            :hint="t('dashboard.noPaymentsHint')"
          />
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
.sub { margin: 2px 0 0; font-size: var(--text-sm); }
.board-card .board-body { padding: var(--sp-6); }

.rail {
  display: grid;
  grid-template-columns: 1.4fr repeat(3, 1fr);
  gap: var(--sp-4);
}
@media (max-width: 980px) {
  .rail { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
  .rail { grid-template-columns: minmax(0, 1fr); }
  .figure { font-size: 27px; }
  .board-card .board-body { padding: var(--sp-4); }
}

.metric { padding: var(--sp-5) var(--sp-6); }

/* One filled card, and only one — the figure the landlord opens the app for. */
.metric.lead {
  background: var(--accent);
  border-color: transparent;
  color: var(--accent-contrast);
}
.metric.lead .eyebrow,
.metric.lead .note { color: var(--accent-contrast); opacity: 0.75; }
.metric.lead .figure { color: var(--accent-contrast); }
.metric.lead .meter { background: rgba(255, 255, 255, 0.22); }
.metric.lead .meter { background: color-mix(in srgb, var(--accent-contrast) 20%, transparent); }
.metric.lead .meter span { background: var(--accent-contrast); }
.figure {
  margin: 8px 0 3px;
  font-size: var(--text-3xl);
  font-weight: 600;
  letter-spacing: -0.03em;
  color: var(--money);
}
.figure.small { font-size: 23px; color: var(--text); }
.note { margin: 0; font-size: var(--text-sm); }
.metric.alarm .figure { color: var(--negative); }

.meter {
  margin-top: var(--sp-3);
  height: 5px;
  border-radius: 3px;
  background: var(--surface-3);
  overflow: hidden;
}
.meter span {
  display: block;
  height: 100%;
  border-radius: 3px;
  background: var(--money);
  transition: width 0.7s var(--ease);
}

.sk-eyebrow { display: block; height: 9px; width: 45%; }
.sk-figure { display: block; height: 24px; width: 70%; margin: 10px 0 8px; }
.sk-note { display: block; height: 9px; width: 60%; }

.unit { font-size: var(--text-sm); }
.owed { color: var(--negative); font-weight: 500; }
.paid { color: var(--positive); font-weight: 500; }
</style>
