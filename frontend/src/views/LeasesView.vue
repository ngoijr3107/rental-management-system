<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { leases, tenants as tenantsApi, units as unitsApi } from '@/api/resources'
import { errorMessage, fieldErrors } from '@/api/client'
import { useCollection } from '@/composables/useCollection'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatMoney, today } from '@/composables/format'
import PageHeader from '@/components/PageHeader.vue'
import PagerBar from '@/components/PagerBar.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import FormField from '@/components/FormField.vue'
import BillingPeriodPicker from '@/components/BillingPeriodPicker.vue'
import EmptyState from '@/components/EmptyState.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'
import StatusPill from '@/components/StatusPill.vue'
import AppIcon from '@/components/AppIcon.vue'

const toast = useToastStore()
const auth = useAuthStore()
const { t } = useI18n()
const { items, meta, loading, filters, load, goToPage } = useCollection(leases.list, { status: '' })

const STATUSES = ['active', 'terminated', 'expired']


const vacantUnits = ref([])
const tenantOptions = ref([])
const creating = ref(false)
const busy = ref(false)
const errors = ref({})
const form = reactive({
  unit_id: '',
  tenant_id: '',
  start_date: today(),
  end_date: '',
  rent_amount: '',
  deposit_amount: '',
  billing_day: 1,
  billing_period_months: 1,
  notes: '',
})

const selectedUnit = computed(() =>
  vacantUnits.value.find((unit) => unit.id === Number(form.unit_id)),
)

/** What one invoice will be, so the figure is confirmed before saving. */
const perInvoice = computed(() => {
  const monthly = Number(form.rent_amount || selectedUnit.value?.rent_amount || 0)
  return monthly * (Number(form.billing_period_months) || 1)
})

async function loadOptions() {
  const [unitsResponse, tenantsResponse] = await Promise.all([
    unitsApi.list({ status: 'vacant', per_page: 100 }),
    tenantsApi.list({ per_page: 100 }),
  ])
  vacantUnits.value = unitsResponse.data.data ?? []
  tenantOptions.value = tenantsResponse.data.data ?? []
}

async function openCreate() {
  await loadOptions()
  errors.value = {}
  Object.assign(form, {
    unit_id: vacantUnits.value[0]?.id ?? '',
    tenant_id: tenantOptions.value[0]?.id ?? '',
    start_date: today(),
    end_date: '',
    rent_amount: '',
    deposit_amount: '',
    billing_day: 1,
    billing_period_months: 1,
    notes: '',
  })
  creating.value = true
}

async function save() {
  busy.value = true
  errors.value = {}
  try {
    const payload = { ...form }
    // Blank means "use the unit's own figures".
    if (payload.rent_amount === '') delete payload.rent_amount
    if (payload.deposit_amount === '') delete payload.deposit_amount
    if (payload.end_date === '') delete payload.end_date

    await leases.create(payload)
    toast.success(t('leases.created'))
    creating.value = false
    await load()
  } catch (error) {
    errors.value = fieldErrors(error)
    if (!Object.keys(errors.value).length) toast.error(errorMessage(error))
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="stack">
    <PageHeader :title="t('leases.title')" :eyebrow="t('nav.letting')" :count="meta?.total ?? null">
      <template #actions>
        <button class="btn" type="button" @click="openCreate"><AppIcon name="plus" :size="15" />{{ t('leases.start') }}</button>
      </template>
    </PageHeader>

    <section class="card">
      <div class="filters">
        <div class="field">
          <label for="status">{{ t('common.status') }}</label>
          <select id="status" v-model="filters.status" class="select">
            <option value="">{{ t('leases.allLeases') }}</option>
            <option v-for="s in STATUSES" :key="s" :value="s">{{ t(`status.${s}`) }}</option>
          </select>
        </div>
      </div>

      <SkeletonTable v-if="loading" :columns="5" />

      <template v-else>
        <div class="table-scroll" v-if="items.length">
          <table class="table">
            <thead>
              <tr>
                <th>{{ t('leases.tenant') }}</th>
                <th>{{ t('leases.unit') }}</th>
                <th>{{ t('leases.term') }}</th>
                <th>{{ t('leases.term') }}</th>
                <th>{{ t('common.status') }}</th>
                <th class="right">{{ t('leases.rent') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="lease in items" :key="lease.id">
                <td>
                  <RouterLink :to="`/leases/${lease.id}`" class="strong-link">
                    {{ lease.tenant?.full_name ?? t('leases.detailEyebrow') }}
                  </RouterLink>
                </td>
                <td class="muted">
                  {{ lease.unit?.label
                  }}<template v-if="lease.unit?.property">, {{ lease.unit.property.name }}</template>
                </td>
                <td class="num muted">
                  {{ formatDate(lease.start_date) }} →
                  {{ lease.end_date ? formatDate(lease.end_date) : t('leases.open') }}
                </td>
                <td class="muted">{{ t('cycle.everyMonths', lease.billing_period_months ?? 1) }}</td>
                <td><StatusPill :status="lease.status" /></td>
                <td class="right num">{{ formatMoney(lease.rent_amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState v-else :title="t('leases.empty')" :hint="t('leases.emptyHint')">
          <button class="btn" type="button" @click="openCreate"><AppIcon name="plus" :size="15" />{{ t('leases.start') }}</button>
        </EmptyState>
      </template>

      <PagerBar :meta="meta" @page="goToPage" />
    </section>

    <ModalDialog v-if="creating" :title="t('leases.startTitle')" @close="creating = false">
      <p v-if="!vacantUnits.length" class="notice">{{ t('leases.noVacant') }}</p>
      <p v-else-if="!tenantOptions.length" class="notice">{{ t('leases.noTenants') }}</p>

      <form v-else class="stack-form" @submit.prevent="save">
        <FormField :label="t('leases.vacantUnit')" name="unit_id" :errors="errors">
          <select v-model="form.unit_id" class="select" required>
            <option v-for="unit in vacantUnits" :key="unit.id" :value="unit.id">
              {{ unit.property?.name }} — {{ unit.label }} ({{ formatMoney(unit.rent_amount) }})
            </option>
          </select>
        </FormField>

        <FormField :label="t('leases.tenant')" name="tenant_id" :errors="errors">
          <select v-model="form.tenant_id" class="select" required>
            <option v-for="tenant in tenantOptions" :key="tenant.id" :value="tenant.id">
              {{ tenant.full_name }} · {{ tenant.phone }}
            </option>
          </select>
        </FormField>

        <FormField
          :label="t('leases.term')"
          name="billing_period_months"
          :errors="errors"
          :hint="t('leases.termHint')"
        >
          <BillingPeriodPicker v-model="form.billing_period_months" />
        </FormField>

        <div class="grid-2">
          <FormField v-slot="{ invalid }" :label="t('leases.starts')" name="start_date" :errors="errors">
            <input
              v-model="form.start_date"
              class="input num"
              :class="{ 'is-invalid': invalid }"
              type="date"
              required
            />
          </FormField>
          <FormField
            :label="t('leases.ends')"
            name="end_date"
            :errors="errors"
            :hint="t('leases.endsHint')"
          >
            <input v-model="form.end_date" class="input num" type="date" />
          </FormField>
        </div>

        <div class="grid-2">
          <FormField
            :label="t('leases.monthlyRent')"
            name="rent_amount"
            :errors="errors"
            :hint="selectedUnit ? t('leases.unitAsks', { amount: formatMoney(selectedUnit.rent_amount) }) : ''"
          >
            <input
              v-model="form.rent_amount"
              class="input num"
              type="number"
              step="0.01"
              min="0"
              :placeholder="t('leases.useUnitRent')"
            />
          </FormField>
          <FormField
            v-if="auth.settings?.collect_deposit !== false"
            :label="t('leases.deposit')"
            name="deposit_amount"
            :errors="errors"
          >
            <input
              v-model="form.deposit_amount"
              class="input num"
              type="number"
              step="0.01"
              min="0"
              :placeholder="t('leases.useUnitDeposit')"
            />
          </FormField>
        </div>

        <FormField
          :label="t('leases.billsOnDay')"
          name="billing_day"
          :errors="errors"
          :hint="t('leases.billingDayHint')"
        >
          <input v-model.number="form.billing_day" class="input num" type="number" min="1" max="28" />
        </FormField>

        <p v-if="perInvoice > 0" class="summary">
          {{
            t('leases.termSummary', { amount: formatMoney(perInvoice) })
          }}
        </p>

        <FormField :label="t('common.notes')" name="notes" :errors="errors">
          <textarea v-model="form.notes" class="textarea" />
        </FormField>
      </form>

      <template #actions>
        <button class="btn btn-ghost" type="button" @click="creating = false">
          {{ t('common.cancel') }}
        </button>
        <button
          class="btn"
          type="button"
          :disabled="busy || !vacantUnits.length || !tenantOptions.length"
          @click="save"
        >
          {{ busy ? t('leases.starting') : t('leases.start') }}
        </button>
      </template>
    </ModalDialog>
  </div>
</template>

<style scoped>
.stack-form { display: flex; flex-direction: column; gap: var(--sp-3); }
.notice { margin: 0; color: var(--text-muted); }
.summary {
  margin: 0;
  padding: var(--sp-3) var(--sp-4);
  border-radius: var(--radius-sm);
  background: var(--accent-wash);
  color: var(--accent-ink);
  font-size: var(--text-base);
}
</style>
