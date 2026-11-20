<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { invoices } from '@/api/resources'
import { errorMessage } from '@/api/client'
import { useCollection } from '@/composables/useCollection'
import { useToastStore } from '@/stores/toast'
import { currentMonth, formatDate, formatMoney, formatMonth } from '@/composables/format'
import PageHeader from '@/components/PageHeader.vue'
import PagerBar from '@/components/PagerBar.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import FormField from '@/components/FormField.vue'
import EmptyState from '@/components/EmptyState.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'
import StatusPill from '@/components/StatusPill.vue'
import AppIcon from '@/components/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const { t } = useI18n()

const { items, meta, loading, filters, load, goToPage } = useCollection(invoices.list, {
  status: '',
  overdue: route.query.overdue ?? '',
  from: '',
  to: '',
})

const STATUSES = ['unpaid', 'partially_paid', 'paid', 'cancelled']

const billing = ref(false)
const busy = ref(false)
const month = ref(currentMonth())

async function runBilling() {
  busy.value = true
  try {
    const { data } = await invoices.generate({ month: month.value })
    const label = formatMonth(`${month.value}-01`)
    toast.success(
      data.count
        ? t('invoices.generated', { count: data.count, month: label })
        : t('invoices.alreadyBilled', { month: label }),
    )
    billing.value = false
    await load()
  } catch (error) {
    toast.error(errorMessage(error, t('invoices.generateFailed')))
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="stack">
    <PageHeader :title="t('invoices.title')" :eyebrow="t('nav.money')" :count="meta?.total ?? null">
      <template #actions>
        <button class="btn" type="button" @click="billing = true"><AppIcon name="plus" :size="15" />{{ t('invoices.raise') }}</button>
      </template>
    </PageHeader>

    <section class="card">
      <div class="filters">
        <div class="field">
          <label for="status">{{ t('common.status') }}</label>
          <select id="status" v-model="filters.status" class="select">
            <option value="">{{ t('common.anyStatus') }}</option>
            <option v-for="s in STATUSES" :key="s" :value="s">{{ t(`status.${s}`) }}</option>
          </select>
        </div>
        <div class="field">
          <label for="overdue">{{ t('invoices.overdueOnly') }}</label>
          <select id="overdue" v-model="filters.overdue" class="select">
            <option value="">{{ t('common.no') }}</option>
            <option value="1">{{ t('common.yes') }}</option>
          </select>
        </div>
        <div class="field">
          <label for="from">{{ t('invoices.periodFrom') }}</label>
          <input id="from" v-model="filters.from" class="input num" type="date" />
        </div>
        <div class="field">
          <label for="to">{{ t('invoices.periodTo') }}</label>
          <input id="to" v-model="filters.to" class="input num" type="date" />
        </div>
      </div>

      <SkeletonTable v-if="loading" :columns="6" />

      <template v-else>
        <div class="table-scroll" v-if="items.length">
          <table class="table">
            <thead>
              <tr>
                <th>{{ t('invoices.number') }}</th>
                <th>{{ t('leases.tenant') }}</th>
                <th>{{ t('leases.unit') }}</th>
                <th>{{ t('invoices.due') }}</th>
                <th>{{ t('common.status') }}</th>
                <th class="right">{{ t('common.amount') }}</th>
                <th class="right">{{ t('invoices.owed') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="invoice in items"
                :key="invoice.id"
                class="row-link"
                @click="router.push(`/invoices/${invoice.id}`)"
              >
                <td class="num strong-link">{{ invoice.invoice_number }}</td>
                <td>{{ invoice.lease?.tenant?.full_name ?? '—' }}</td>
                <td class="muted">{{ invoice.lease?.unit?.label ?? '—' }}</td>
                <td class="num" :class="{ late: invoice.is_overdue }">
                  {{ formatDate(invoice.due_date) }}
                </td>
                <td><StatusPill :status="invoice.is_overdue ? 'overdue' : invoice.status" /></td>
                <td class="right num">{{ formatMoney(invoice.amount) }}</td>
                <td class="right num" :class="Number(invoice.balance) > 0 ? 'owed' : 'settled'">
                  {{ formatMoney(invoice.balance) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState v-else :title="t('invoices.empty')" :hint="t('invoices.emptyHint')">
          <button class="btn" type="button" @click="billing = true"><AppIcon name="plus" :size="15" />{{ t('invoices.raise') }}</button>
        </EmptyState>
      </template>

      <PagerBar :meta="meta" @page="goToPage" />
    </section>

    <ModalDialog
      v-if="billing"
      :title="t('invoices.raiseTitle')"
      :subtitle="t('invoices.raiseSubtitle')"
      width="420px"
      @close="billing = false"
    >
      <FormField :label="t('invoices.billingMonth')" name="month" :hint="t('invoices.billingMonthHint')">
        <input v-model="month" class="input num" type="month" />
      </FormField>
      <template #actions>
        <button class="btn btn-ghost" type="button" @click="billing = false">
          {{ t('common.cancel') }}
        </button>
        <button class="btn" type="button" :disabled="busy" @click="runBilling">
          {{ busy ? t('invoices.raising') : t('invoices.raiseAction') }}
        </button>
      </template>
    </ModalDialog>
  </div>
</template>

<style scoped>
.owed { color: var(--negative); font-weight: 500; }
.settled { color: var(--text-faint); }
.late { color: var(--negative); }
</style>
