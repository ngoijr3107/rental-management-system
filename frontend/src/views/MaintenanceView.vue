<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { maintenance, tenants as tenantsApi, units as unitsApi } from '@/api/resources'
import { errorMessage, fieldErrors } from '@/api/client'
import { useCollection } from '@/composables/useCollection'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatMoney, today } from '@/composables/format'
import PageHeader from '@/components/PageHeader.vue'
import PagerBar from '@/components/PagerBar.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import FormField from '@/components/FormField.vue'
import EmptyState from '@/components/EmptyState.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'
import StatusPill from '@/components/StatusPill.vue'
import RowAction from '@/components/RowAction.vue'
import AppIcon from '@/components/AppIcon.vue'

const toast = useToastStore()
const { t } = useI18n()
const { items, meta, loading, filters, load, goToPage } = useCollection(maintenance.list, {
  status: '',
  priority: '',
})

const STATUSES = ['open', 'in_progress', 'resolved', 'cancelled']
const PRIORITIES = ['low', 'medium', 'high', 'urgent']

const unitOptions = ref([])
const tenantOptions = ref([])
const editing = ref(null)
const removing = ref(null)
const busy = ref(false)
const errors = ref({})
const form = reactive({
  unit_id: '',
  tenant_id: '',
  title: '',
  description: '',
  priority: 'medium',
  status: 'open',
  cost: '',
  reported_at: today(),
})

async function loadOptions() {
  const [unitsResponse, tenantsResponse] = await Promise.all([
    unitsApi.list({ per_page: 100 }),
    tenantsApi.list({ per_page: 100 }),
  ])
  unitOptions.value = unitsResponse.data.data ?? []
  tenantOptions.value = tenantsResponse.data.data ?? []
}

function openCreate() {
  editing.value = { id: null }
  errors.value = {}
  Object.assign(form, {
    unit_id: unitOptions.value[0]?.id ?? '',
    tenant_id: '',
    title: '',
    description: '',
    priority: 'medium',
    status: 'open',
    cost: '',
    reported_at: today(),
  })
}

function openEdit(item) {
  editing.value = item
  errors.value = {}
  Object.assign(form, {
    unit_id: item.unit_id,
    tenant_id: item.tenant_id ?? '',
    title: item.title,
    description: item.description ?? '',
    priority: item.priority,
    status: item.status,
    cost: item.cost ?? '',
    reported_at: item.reported_at ?? today(),
  })
}

async function save() {
  busy.value = true
  errors.value = {}
  try {
    const payload = {
      ...form,
      tenant_id: form.tenant_id === '' ? null : form.tenant_id,
      cost: form.cost === '' ? null : form.cost,
    }
    if (editing.value.id) {
      await maintenance.update(editing.value.id, payload)
      toast.success(t('maintenance.updated'))
    } else {
      await maintenance.create(payload)
      toast.success(t('maintenance.logged'))
    }
    editing.value = null
    await load()
  } catch (error) {
    errors.value = fieldErrors(error)
    if (!Object.keys(errors.value).length) toast.error(errorMessage(error))
  } finally {
    busy.value = false
  }
}

/** One-click close from the list — the common case. */
async function resolve(item) {
  try {
    await maintenance.update(item.id, { status: 'resolved' })
    toast.success(t('maintenance.resolved'))
    await load()
  } catch (error) {
    toast.error(errorMessage(error))
  }
}

async function remove() {
  busy.value = true
  try {
    await maintenance.remove(removing.value.id)
    toast.success(t('maintenance.deleted'))
    removing.value = null
    await load()
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  await loadOptions()
  await load()
})
</script>

<template>
  <div class="stack">
    <PageHeader
      :title="t('maintenance.title')"
      :eyebrow="t('nav.upkeep')"
      :count="meta?.total ?? null"
    >
      <template #actions>
        <button class="btn" type="button" :disabled="!unitOptions.length" @click="openCreate"><AppIcon name="plus" :size="15" />{{ t('maintenance.log') }}</button>
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
          <label for="priority">{{ t('maintenance.priority') }}</label>
          <select id="priority" v-model="filters.priority" class="select">
            <option value="">{{ t('maintenance.anyPriority') }}</option>
            <option v-for="p in PRIORITIES" :key="p" :value="p">{{ t(`status.${p}`) }}</option>
          </select>
        </div>
      </div>

      <SkeletonTable v-if="loading" :columns="6" />

      <template v-else>
        <div class="table-scroll" v-if="items.length">
          <table class="table">
            <thead>
              <tr>
                <th>{{ t('maintenance.job') }}</th>
                <th>{{ t('maintenance.unit') }}</th>
                <th>{{ t('maintenance.reported') }}</th>
                <th>{{ t('maintenance.priority') }}</th>
                <th>{{ t('common.status') }}</th>
                <th class="right">{{ t('maintenance.cost') }}</th>
                <th class="right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.id">
                <td class="title">{{ item.title }}</td>
                <td class="muted">
                  {{ item.unit?.label ?? '—' }}
                  <span v-if="item.unit?.property">· {{ item.unit.property.name }}</span>
                </td>
                <td class="num muted">{{ formatDate(item.reported_at) }}</td>
                <td><StatusPill :status="item.priority" /></td>
                <td><StatusPill :status="item.status" /></td>
                <td class="right num">{{ item.cost ? formatMoney(item.cost) : '—' }}</td>
                <td class="actions">
                  <RowAction v-if="item.status !== 'resolved' && item.status !== 'cancelled'" icon="check" tone="positive" :label="t('maintenance.resolve')" @click="resolve(item)" />
                  <RowAction icon="edit" :label="t('common.edit')" @click="openEdit(item)" />
                  <RowAction icon="trash" tone="danger" :label="t('common.delete')" @click="removing = item" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState
          v-else-if="unitOptions.length"
          :title="t('maintenance.empty')"
          :hint="t('maintenance.emptyHint')"
        >
          <button class="btn" type="button" @click="openCreate"><AppIcon name="plus" :size="15" />{{ t('maintenance.log') }}</button>
        </EmptyState>

        <EmptyState v-else :title="t('maintenance.noUnits')" :hint="t('maintenance.noUnitsHint')">
          <RouterLink to="/units" class="btn">{{ t('maintenance.goToUnits') }}</RouterLink>
        </EmptyState>
      </template>

      <PagerBar :meta="meta" @page="goToPage" />
    </section>

    <ModalDialog
      v-if="editing"
      :title="editing.id ? t('maintenance.editTitle') : t('maintenance.logTitle')"
      @close="editing = null"
    >
      <form class="stack-form" @submit.prevent="save">
        <FormField :label="t('maintenance.unit')" name="unit_id" :errors="errors">
          <select v-model="form.unit_id" class="select" required>
            <option v-for="unit in unitOptions" :key="unit.id" :value="unit.id">
              {{ unit.property?.name }} — {{ unit.label }}
            </option>
          </select>
        </FormField>
        <FormField v-slot="{ invalid }" :label="t('maintenance.what')" name="title" :errors="errors">
          <input v-model="form.title" class="input" :class="{ 'is-invalid': invalid }" required />
        </FormField>
        <FormField :label="t('maintenance.details')" name="description" :errors="errors">
          <textarea v-model="form.description" class="textarea" />
        </FormField>
        <div class="grid-2">
          <FormField :label="t('maintenance.priority')" name="priority" :errors="errors">
            <select v-model="form.priority" class="select">
              <option v-for="p in PRIORITIES" :key="p" :value="p">{{ t(`status.${p}`) }}</option>
            </select>
          </FormField>
          <FormField :label="t('common.status')" name="status" :errors="errors">
            <select v-model="form.status" class="select">
              <option v-for="s in STATUSES" :key="s" :value="s">{{ t(`status.${s}`) }}</option>
            </select>
          </FormField>
        </div>
        <div class="grid-2">
          <FormField :label="t('maintenance.reportedOn')" name="reported_at" :errors="errors">
            <input v-model="form.reported_at" class="input num" type="date" />
          </FormField>
          <FormField
            :label="t('maintenance.cost')"
            name="cost"
            :errors="errors"
            :hint="t('maintenance.costHint')"
          >
            <input v-model="form.cost" class="input num" type="number" step="0.01" min="0" />
          </FormField>
        </div>
        <FormField
          :label="t('maintenance.reportedBy')"
          name="tenant_id"
          :errors="errors"
          :hint="t('common.optional')"
        >
          <select v-model="form.tenant_id" class="select">
            <option value="">{{ t('maintenance.nobody') }}</option>
            <option v-for="tenant in tenantOptions" :key="tenant.id" :value="tenant.id">
              {{ tenant.full_name }}
            </option>
          </select>
        </FormField>
      </form>
      <template #actions>
        <button class="btn btn-ghost" type="button" @click="editing = null">
          {{ t('common.cancel') }}
        </button>
        <button class="btn" type="button" :disabled="busy" @click="save">
          {{ busy ? t('common.saving') : editing.id ? t('common.save') : t('maintenance.logAction') }}
        </button>
      </template>
    </ModalDialog>

    <ConfirmDialog
      v-if="removing"
      :title="t('maintenance.deleteTitle')"
      :message="t('maintenance.deleteMessage', { title: removing.title })"
      :confirm-label="t('common.delete')"
      danger
      :busy="busy"
      @close="removing = null"
      @confirm="remove"
    />
  </div>
</template>

<style scoped>
.nowrap { white-space: nowrap; }
.title { font-weight: 500; }
.stack-form { display: flex; flex-direction: column; gap: var(--sp-3); }
</style>
