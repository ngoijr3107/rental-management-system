<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { payments } from '@/api/resources'
import { errorMessage } from '@/api/client'
import { useCollection } from '@/composables/useCollection'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatMoney } from '@/composables/format'
import PageHeader from '@/components/PageHeader.vue'
import PagerBar from '@/components/PagerBar.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import EmptyState from '@/components/EmptyState.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'
import StatusPill from '@/components/StatusPill.vue'
import RowAction from '@/components/RowAction.vue'

const toast = useToastStore()
const auth = useAuthStore()
const { t } = useI18n()
const { items, meta, loading, filters, load, goToPage } = useCollection(payments.list, {
  method: '',
  from: '',
  to: '',
})

const METHODS = ['cash', 'bank_transfer', 'mobile_money', 'cheque', 'card']

const reversing = ref(null)
const busy = ref(false)

const pageTotal = computed(() =>
  items.value.reduce((sum, payment) => sum + Number(payment.amount ?? 0), 0),
)

async function reverse() {
  busy.value = true
  try {
    await payments.remove(reversing.value.id)
    toast.success(t('payments.reversed'))
    reversing.value = null
    await load()
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="stack">
    <PageHeader :title="t('payments.title')" :eyebrow="t('nav.money')" :count="meta?.total ?? null" />

    <section class="card">
      <div class="filters">
        <div class="field">
          <label for="method">{{ t('payments.method') }}</label>
          <select id="method" v-model="filters.method" class="select">
            <option value="">{{ t('payments.anyMethod') }}</option>
            <option v-for="m in METHODS" :key="m" :value="m">{{ t(`status.${m}`) }}</option>
          </select>
        </div>
        <div class="field">
          <label for="from">{{ t('payments.receivedFrom') }}</label>
          <input id="from" v-model="filters.from" class="input num" type="date" />
        </div>
        <div class="field">
          <label for="to">{{ t('payments.receivedTo') }}</label>
          <input id="to" v-model="filters.to" class="input num" type="date" />
        </div>
        <div class="spacer" />
        <div class="page-total">
          <p class="eyebrow">{{ t('payments.pageTotal') }}</p>
          <p class="num total">{{ formatMoney(pageTotal) }}</p>
        </div>
      </div>

      <SkeletonTable v-if="loading" :columns="6" />

      <template v-else>
        <div class="table-scroll" v-if="items.length">
          <table class="table">
            <thead>
              <tr>
                <th>{{ t('payments.received') }}</th>
                <th>{{ t('leases.tenant') }}</th>
                <th>{{ t('payments.invoice') }}</th>
                <th>{{ t('payments.method') }}</th>
                <th>{{ t('payments.reference') }}</th>
                <th class="right">{{ t('common.amount') }}</th>
                <th class="right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="payment in items" :key="payment.id">
                <td class="num">{{ formatDate(payment.paid_at) }}</td>
                <td>{{ payment.invoice?.lease?.tenant?.full_name ?? '—' }}</td>
                <td>
                  <RouterLink
                    v-if="payment.invoice"
                    :to="`/invoices/${payment.invoice.id}`"
                    class="strong-link num"
                  >
                    {{ payment.invoice.invoice_number }}
                  </RouterLink>
                  <span v-else class="muted">—</span>
                </td>
                <td><StatusPill :status="payment.method" tone="neutral" /></td>
                <td class="num muted">{{ payment.reference || '—' }}</td>
                <td class="right num amount">{{ formatMoney(payment.amount, true) }}</td>
                <td class="right">
                  <RowAction v-if="auth.can('payments.reverse')" icon="undo" tone="danger" :label="t('payments.reverse')" @click="reversing = payment" />
                  <span v-else class="muted">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState v-else :title="t('payments.empty')" :hint="t('payments.emptyHint')">
          <RouterLink to="/invoices" class="btn">{{ t('payments.goToInvoices') }}</RouterLink>
        </EmptyState>
      </template>

      <PagerBar :meta="meta" @page="goToPage" />
    </section>

    <ConfirmDialog
      v-if="reversing"
      :title="t('payments.reverseTitle')"
      :message="t('payments.reverseMessage', { amount: formatMoney(reversing.amount, true) })"
      :confirm-label="t('payments.reverseAction')"
      danger
      :busy="busy"
      @close="reversing = null"
      @confirm="reverse"
    />
  </div>
</template>

<style scoped>
.amount { color: var(--positive); font-weight: 500; }
.page-total { text-align: right; }
.total { margin: 2px 0 0; font-size: 17px; font-weight: 600; color: var(--money); }
</style>
