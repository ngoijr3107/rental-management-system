<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { leases } from '@/api/resources'
import { errorMessage, fieldErrors } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { currentMonth, formatDate, formatMoney, formatMonth, today } from '@/composables/format'
import PageHeader from '@/components/PageHeader.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import FormField from '@/components/FormField.vue'
import BillingPeriodPicker from '@/components/BillingPeriodPicker.vue'
import StatusPill from '@/components/StatusPill.vue'
import EmptyState from '@/components/EmptyState.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })

const router = useRouter()
const toast = useToastStore()
const auth = useAuthStore()
const { t } = useI18n()

const lease = ref(null)
const loading = ref(true)
const editing = ref(false)
const ending = ref(false)
const billing = ref(false)
const busy = ref(false)
const errors = ref({})

const form = reactive({
  end_date: '',
  rent_amount: '',
  deposit_amount: '',
  billing_day: 1,
  billing_period_months: 1,
  notes: '',
})

const terminatedAt = ref(today())
const month = ref(currentMonth())

const outstanding = computed(() =>
  (lease.value?.invoices ?? []).reduce((sum, invoice) => sum + Number(invoice.balance ?? 0), 0),
)

const billed = computed(() =>
  (lease.value?.invoices ?? []).reduce((sum, invoice) => sum + Number(invoice.amount ?? 0), 0),
)

async function load() {
  loading.value = true
  try {
    const { data } = await leases.get(props.id)
    lease.value = data.data
  } catch (error) {
    toast.error(errorMessage(error, t('leases.loadFailed')))
  } finally {
    loading.value = false
  }
}

function openEdit() {
  errors.value = {}
  Object.assign(form, {
    end_date: lease.value.end_date ?? '',
    rent_amount: lease.value.rent_amount,
    deposit_amount: lease.value.deposit_amount,
    billing_day: lease.value.billing_day,
    billing_period_months: lease.value.billing_period_months ?? 1,
    notes: lease.value.notes ?? '',
  })
  editing.value = true
}

async function save() {
  busy.value = true
  errors.value = {}
  try {
    const payload = { ...form }
    if (payload.end_date === '') payload.end_date = null
    await leases.update(props.id, payload)
    toast.success(t('leases.updated'))
    editing.value = false
    await load()
  } catch (error) {
    errors.value = fieldErrors(error)
    if (!Object.keys(errors.value).length) toast.error(errorMessage(error))
  } finally {
    busy.value = false
  }
}

async function terminate() {
  busy.value = true
  try {
    await leases.terminate(props.id, { terminated_at: terminatedAt.value })
    toast.success(t('leases.terminated'))
    ending.value = false
    await load()
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    busy.value = false
  }
}

async function billMonth() {
  busy.value = true
  try {
    const { data } = await leases.bill(props.id, { month: month.value })
    toast.success(
      t('leases.invoiceReady', {
        number: data.data.invoice_number,
        month: formatMonth(`${month.value}-01`),
      }),
    )
    billing.value = false
    await load()
  } catch (error) {
    toast.error(errorMessage(error, t('leases.billFailed')))
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="stack">
    <section v-if="loading" class="card"><SkeletonTable :rows="5" :columns="5" /></section>

    <template v-else-if="lease">
      <PageHeader
        :title="lease.tenant?.full_name ?? t('leases.detailEyebrow')"
        :eyebrow="t('leases.detailEyebrow')"
      >
        <template #actions>
          <RouterLink to="/leases" class="btn btn-ghost">{{ t('leases.backToList') }}</RouterLink>
          <button v-if="auth.can('leases.manage')" class="btn btn-ghost" type="button" @click="openEdit">
            {{ t('leases.editTerms') }}
          </button>
          <button class="btn btn-ghost" type="button" @click="billing = true">
            {{ t('leases.billMonth') }}
          </button>
          <button
            v-if="lease.status === 'active' && auth.can('leases.manage')"
            class="btn btn-danger"
            type="button"
            @click="ending = true"
          >
            {{ t('leases.terminate') }}
          </button>
        </template>
      </PageHeader>

      <section class="card terms">
        <dl>
          <div>
            <dt>{{ t('common.status') }}</dt>
            <dd><StatusPill :status="lease.status" /></dd>
          </div>
          <div>
            <dt>{{ t('leases.unit') }}</dt>
            <dd>
              {{ lease.unit?.label ?? '—' }}
              <span class="muted">{{ lease.unit?.property?.name ?? '' }}</span>
            </dd>
          </div>
          <div>
            <dt>{{ t('leases.monthlyRent') }}</dt>
            <dd class="num strong">{{ formatMoney(lease.rent_amount) }}</dd>
          </div>
          <div v-if="auth.settings?.collect_deposit !== false">
            <dt>{{ t('leases.depositHeld') }}</dt>
            <dd class="num">{{ formatMoney(lease.deposit_amount) }}</dd>
          </div>
          <div>
            <dt>{{ t('leases.term') }}</dt>
            <dd class="num">
              {{ formatDate(lease.start_date) }} →
              {{ lease.end_date ? formatDate(lease.end_date) : t('leases.openEnded') }}
            </dd>
          </div>
          <div>
            <dt>{{ t('leases.term') }}</dt>
            <dd>{{ t('cycle.everyMonths', lease.billing_period_months ?? 1) }}</dd>
          </div>
          <div>
            <dt>{{ t('leases.perInvoice') }}</dt>
            <dd class="num strong">{{ formatMoney(lease.period_amount ?? lease.rent_amount) }}</dd>
          </div>
          <div>
            <dt>{{ t('leases.billsOnDay') }}</dt>
            <dd class="num">{{ lease.billing_day }}</dd>
          </div>
          <div v-if="lease.terminated_at">
            <dt>{{ t('leases.terminatedOn') }}</dt>
            <dd class="num">{{ formatDate(lease.terminated_at) }}</dd>
          </div>
          <div>
            <dt>{{ t('common.phone') }}</dt>
            <dd class="num">{{ lease.tenant?.phone ?? '—' }}</dd>
          </div>
        </dl>
        <p v-if="lease.notes" class="notes">{{ lease.notes }}</p>
      </section>

      <section class="card">
        <div class="card-head">
          <div>
            <h3>{{ t('leases.ledger') }}</h3>
            <p class="muted sub">
              {{
                t('leases.ledgerSummary', {
                  billed: formatMoney(billed),
                  outstanding: formatMoney(outstanding),
                })
              }}
            </p>
          </div>
        </div>

        <div class="table-scroll" v-if="lease.invoices?.length">
          <table class="table">
            <thead>
              <tr>
                <th>{{ t('invoices.number') }}</th>
                <th>{{ t('invoices.period') }}</th>
                <th>{{ t('invoices.due') }}</th>
                <th>{{ t('common.status') }}</th>
                <th class="right">{{ t('common.amount') }}</th>
                <th class="right">{{ t('invoices.owed') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="invoice in lease.invoices"
                :key="invoice.id"
                class="row-link"
                @click="router.push(`/invoices/${invoice.id}`)"
              >
                <td class="num strong-link">{{ invoice.invoice_number }}</td>
                <td class="num muted">
                  {{ formatDate(invoice.period_start) }} → {{ formatDate(invoice.period_end) }}
                </td>
                <td class="num">{{ formatDate(invoice.due_date) }}</td>
                <td><StatusPill :status="invoice.is_overdue ? 'overdue' : invoice.status" /></td>
                <td class="right num">{{ formatMoney(invoice.amount) }}</td>
                <td class="right num" :class="Number(invoice.balance) > 0 ? 'owed' : 'settled'">
                  {{ formatMoney(invoice.balance) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState v-else :title="t('leases.nothingBilled')" :hint="t('leases.nothingBilledHint')">
          <button class="btn" type="button" @click="billing = true">{{ t('leases.billMonth') }}</button>
        </EmptyState>
      </section>
    </template>

    <ModalDialog v-if="editing" :title="t('leases.editTermsTitle')" @close="editing = false">
      <form class="stack-form" @submit.prevent="save">
        <div class="grid-2">
          <FormField :label="t('leases.monthlyRent')" name="rent_amount" :errors="errors">
            <input v-model="form.rent_amount" class="input num" type="number" step="0.01" min="0" />
          </FormField>
          <FormField
            v-if="auth.settings?.collect_deposit !== false"
            :label="t('leases.deposit')"
            name="deposit_amount"
            :errors="errors"
          >
            <input v-model="form.deposit_amount" class="input num" type="number" step="0.01" min="0" />
          </FormField>
        </div>
        <FormField
          :label="t('leases.term')"
          name="billing_period_months"
          :errors="errors"
          :hint="t('leases.termHint')"
        >
          <BillingPeriodPicker v-model="form.billing_period_months" />
        </FormField>

        <div class="grid-2">
          <FormField
            :label="t('leases.ends')"
            name="end_date"
            :errors="errors"
            :hint="t('leases.endDateHint')"
          >
            <input v-model="form.end_date" class="input num" type="date" />
          </FormField>
          <FormField
            :label="t('leases.billsOnDay')"
            name="billing_day"
            :errors="errors"
            :hint="t('leases.dayRange')"
          >
            <input v-model.number="form.billing_day" class="input num" type="number" min="1" max="28" />
          </FormField>
        </div>
        <FormField :label="t('common.notes')" name="notes" :errors="errors">
          <textarea v-model="form.notes" class="textarea" />
        </FormField>
      </form>
      <template #actions>
        <button class="btn btn-ghost" type="button" @click="editing = false">
          {{ t('common.cancel') }}
        </button>
        <button class="btn" type="button" :disabled="busy" @click="save">
          {{ busy ? t('common.saving') : t('common.save') }}
        </button>
      </template>
    </ModalDialog>

    <ModalDialog
      v-if="billing"
      :title="t('leases.billMonth')"
      :subtitle="t('leases.billMonthSubtitle')"
      width="420px"
      @close="billing = false"
    >
      <FormField :label="t('leases.billingMonth')" name="month">
        <input v-model="month" class="input num" type="month" />
      </FormField>
      <template #actions>
        <button class="btn btn-ghost" type="button" @click="billing = false">
          {{ t('common.cancel') }}
        </button>
        <button class="btn" type="button" :disabled="busy" @click="billMonth">
          {{ busy ? t('invoices.raising') : t('leases.raiseInvoice') }}
        </button>
      </template>
    </ModalDialog>

    <ConfirmDialog
      v-if="ending"
      :title="t('leases.terminateTitle')"
      :message="t('leases.terminateMessage')"
      :confirm-label="t('leases.terminate')"
      danger
      :busy="busy"
      @close="ending = false"
      @confirm="terminate"
    >
      <div class="field end-date">
        <label for="terminated_at">{{ t('leases.terminationDate') }}</label>
        <input id="terminated_at" v-model="terminatedAt" class="input num" type="date" />
      </div>
    </ConfirmDialog>
  </div>
</template>

<style scoped>
.terms { padding: var(--sp-5); }
.terms dl {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: var(--sp-4) var(--sp-6);
  margin: 0;
}
.terms dt {
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--text-faint);
  margin-bottom: 3px;
}
.terms dd { margin: 0; font-size: var(--text-base); }
.strong { font-weight: 600; font-size: 15px; }
.notes {
  margin: var(--sp-5) 0 0;
  padding-top: var(--sp-4);
  border-top: 1px solid var(--line);
  color: var(--text-muted);
  font-size: var(--text-base);
}
.sub { margin: 2px 0 0; font-size: var(--text-sm); }
.owed { color: var(--negative); font-weight: 500; }
.settled { color: var(--text-faint); }
.stack-form { display: flex; flex-direction: column; gap: var(--sp-3); }
.end-date { margin-top: var(--sp-4); }
</style>
