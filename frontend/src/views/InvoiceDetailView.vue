<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { invoices } from '@/api/resources'
import { errorMessage, fieldErrors } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { daysFromToday, formatDate, formatMoney, today } from '@/composables/format'
import PageHeader from '@/components/PageHeader.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import FormField from '@/components/FormField.vue'
import StatusPill from '@/components/StatusPill.vue'
import EmptyState from '@/components/EmptyState.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })

const toast = useToastStore()
const auth = useAuthStore()
const { t } = useI18n()
const invoice = ref(null)
const loading = ref(true)
const paying = ref(false)
const cancelling = ref(false)
const busy = ref(false)
const errors = ref({})

const METHODS = ['cash', 'bank_transfer', 'mobile_money', 'cheque', 'card']
const form = reactive({ amount: '', paid_at: today(), method: 'cash', reference: '', notes: '' })

const balance = computed(() => Number(invoice.value?.balance ?? 0))
const settled = computed(() => balance.value <= 0)

const dueLine = computed(() => {
  if (!invoice.value) return ''
  if (settled.value) return t('invoices.settledInFull')
  const days = daysFromToday(invoice.value.due_date)
  if (days === null) return ''
  if (days < 0) return t('invoices.daysOverdue', Math.abs(days))
  if (days === 0) return t('invoices.dueToday')
  return t('invoices.dueInDays', days)
})

async function load() {
  loading.value = true
  try {
    const { data } = await invoices.get(props.id)
    invoice.value = data.data
  } catch (error) {
    toast.error(errorMessage(error, t('invoices.loadFailed')))
  } finally {
    loading.value = false
  }
}

function openPayment() {
  errors.value = {}
  Object.assign(form, {
    amount: balance.value > 0 ? balance.value.toFixed(2) : '',
    paid_at: today(),
    method: 'cash',
    reference: '',
    notes: '',
  })
  paying.value = true
}

async function recordPayment() {
  busy.value = true
  errors.value = {}
  try {
    await invoices.pay(props.id, { ...form })
    toast.success(t('invoices.recorded'))
    paying.value = false
    await load()
  } catch (error) {
    errors.value = fieldErrors(error)
    if (!Object.keys(errors.value).length) toast.error(errorMessage(error))
  } finally {
    busy.value = false
  }
}

async function cancelInvoice() {
  busy.value = true
  try {
    await invoices.cancel(props.id)
    toast.success(t('invoices.cancelled'))
    cancelling.value = false
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
    <section v-if="loading" class="card"><SkeletonTable :rows="4" :columns="4" /></section>

    <template v-else-if="invoice">
      <PageHeader :title="invoice.invoice_number" :eyebrow="t('invoices.detailEyebrow')">
        <template #actions>
          <RouterLink to="/invoices" class="btn btn-ghost">{{ t('invoices.backToList') }}</RouterLink>
          <button
            v-if="invoice.status !== 'cancelled' && !settled && auth.can('invoices.manage')"
            class="btn btn-ghost"
            type="button"
            @click="cancelling = true"
          >
            {{ t('invoices.cancel') }}
          </button>
          <button
            v-if="invoice.status !== 'cancelled' && !settled && auth.can('payments.record')"
            class="btn"
            type="button"
            @click="openPayment"
          >
            {{ t('invoices.recordPayment') }}
          </button>
        </template>
      </PageHeader>

      <section class="hero card" :class="{ late: invoice.is_overdue, done: settled }">
        <div class="hero-main">
          <p class="eyebrow">{{ t('invoices.outstanding') }}</p>
          <p class="num figure">{{ formatMoney(invoice.balance, true) }}</p>
          <p class="due">{{ dueLine }}</p>
        </div>
        <dl class="hero-facts">
          <div>
            <dt>{{ t('common.status') }}</dt>
            <dd><StatusPill :status="invoice.is_overdue ? 'overdue' : invoice.status" /></dd>
          </div>
          <div>
            <dt>{{ t('invoices.billed') }}</dt>
            <dd class="num">{{ formatMoney(invoice.amount, true) }}</dd>
          </div>
          <div>
            <dt>{{ t('invoices.paid') }}</dt>
            <dd class="num">{{ formatMoney(invoice.amount_paid, true) }}</dd>
          </div>
          <div>
            <dt>{{ t('invoices.dueDate') }}</dt>
            <dd class="num">{{ formatDate(invoice.due_date) }}</dd>
          </div>
          <div>
            <dt>{{ t('invoices.period') }}</dt>
            <dd class="num">
              {{ formatDate(invoice.period_start) }} → {{ formatDate(invoice.period_end) }}
            </dd>
          </div>
        </dl>
      </section>

      <div class="grid-2">
        <section class="card">
          <div class="card-head"><h3>{{ t('invoices.whoFor') }}</h3></div>
          <dl class="facts">
            <div>
              <dt>{{ t('leases.tenant') }}</dt>
              <dd>{{ invoice.lease?.tenant?.full_name ?? '—' }}</dd>
            </div>
            <div>
              <dt>{{ t('common.phone') }}</dt>
              <dd class="num">{{ invoice.lease?.tenant?.phone ?? '—' }}</dd>
            </div>
            <div>
              <dt>{{ t('leases.unit') }}</dt>
              <dd>
                {{ invoice.lease?.unit?.label ?? '—' }}
                <span class="muted">{{ invoice.lease?.unit?.property?.name ?? '' }}</span>
              </dd>
            </div>
            <div>
              <dt>{{ t('leases.detailEyebrow') }}</dt>
              <dd>
                <RouterLink v-if="invoice.lease" :to="`/leases/${invoice.lease.id}`" class="strong-link">
                  {{ t('invoices.viewLease') }}
                </RouterLink>
              </dd>
            </div>
          </dl>
        </section>

        <section class="card">
          <div class="card-head">
            <h3>{{ t('invoices.payments') }}</h3>
            <span class="muted num">{{ invoice.payments?.length ?? 0 }}</span>
          </div>
          <div class="table-scroll" v-if="invoice.payments?.length">
            <table class="table">
              <thead>
                <tr>
                  <th>{{ t('payments.received') }}</th>
                  <th>{{ t('payments.method') }}</th>
                  <th>{{ t('payments.reference') }}</th>
                  <th class="right">{{ t('common.amount') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="payment in invoice.payments" :key="payment.id">
                  <td class="num">{{ formatDate(payment.paid_at) }}</td>
                  <td><StatusPill :status="payment.method" tone="neutral" /></td>
                  <td class="num muted">{{ payment.reference || '—' }}</td>
                  <td class="right num amount">{{ formatMoney(payment.amount, true) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <EmptyState
            v-else
            :title="t('invoices.nothingReceived')"
            :hint="t('invoices.nothingReceivedHint')"
          />
        </section>
      </div>
    </template>

    <ModalDialog
      v-if="paying"
      :title="t('invoices.recordPayment')"
      :subtitle="
        t('invoices.outstandingOn', {
          number: invoice.invoice_number,
          amount: formatMoney(invoice.balance, true),
        })
      "
      width="440px"
      @close="paying = false"
    >
      <form class="stack-form" @submit.prevent="recordPayment">
        <FormField
          v-slot="{ invalid }"
          :label="t('common.amount')"
          name="amount"
          :errors="errors"
          :hint="t('invoices.amountHint')"
        >
          <input
            v-model="form.amount"
            class="input num"
            :class="{ 'is-invalid': invalid }"
            type="number"
            step="0.01"
            min="0.01"
            required
          />
        </FormField>
        <div class="grid-2">
          <FormField :label="t('invoices.receivedOn')" name="paid_at" :errors="errors">
            <input v-model="form.paid_at" class="input num" type="date" :max="today()" />
          </FormField>
          <FormField :label="t('invoices.method')" name="method" :errors="errors">
            <select v-model="form.method" class="select">
              <option v-for="m in METHODS" :key="m" :value="m">{{ t(`status.${m}`) }}</option>
            </select>
          </FormField>
        </div>
        <FormField
          :label="t('invoices.reference')"
          name="reference"
          :errors="errors"
          :hint="t('invoices.referenceHint')"
        >
          <input v-model="form.reference" class="input" />
        </FormField>
        <FormField :label="t('common.notes')" name="notes" :errors="errors">
          <textarea v-model="form.notes" class="textarea" />
        </FormField>
      </form>
      <template #actions>
        <button class="btn btn-ghost" type="button" @click="paying = false">
          {{ t('common.cancel') }}
        </button>
        <button class="btn" type="button" :disabled="busy" @click="recordPayment">
          {{ busy ? t('invoices.recording') : t('invoices.recordPayment') }}
        </button>
      </template>
    </ModalDialog>

    <ConfirmDialog
      v-if="cancelling"
      :title="t('invoices.cancel')"
      :message="t('invoices.cancelMessage')"
      :confirm-label="t('invoices.cancel')"
      danger
      :busy="busy"
      @close="cancelling = false"
      @confirm="cancelInvoice"
    />
  </div>
</template>

<style scoped>
.hero {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--sp-8);
  padding: var(--sp-6);
  border-left: 3px solid var(--money);
}
.hero.late { border-left-color: var(--negative); }
.hero.done { border-left-color: var(--positive); }
.hero-main { min-width: 200px; }
.figure {
  margin: 6px 0 2px;
  font-size: 34px;
  font-weight: 600;
  letter-spacing: -0.03em;
  color: var(--money);
}
.hero.late .figure { color: var(--negative); }
.hero.done .figure { color: var(--positive); }
.due { margin: 0; font-size: var(--text-sm); color: var(--text-muted); }

.hero-facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: var(--sp-4) var(--sp-6);
  flex: 1;
  margin: 0;
}
.hero-facts dt,
.facts dt {
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--text-faint);
  margin-bottom: 3px;
}
.hero-facts dd,
.facts dd { margin: 0; font-size: var(--text-base); }

.facts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--sp-4);
  margin: 0;
  padding: var(--sp-5);
}
.amount { color: var(--positive); font-weight: 500; }
.stack-form { display: flex; flex-direction: column; gap: var(--sp-3); }
</style>
