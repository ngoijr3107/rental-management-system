<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { properties, units } from '@/api/resources'
import { errorMessage, fieldErrors } from '@/api/client'
import { useCollection } from '@/composables/useCollection'
import { useToastStore } from '@/stores/toast'
import { formatMoney } from '@/composables/format'
import PageHeader from '@/components/PageHeader.vue'
import PagerBar from '@/components/PagerBar.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import FormField from '@/components/FormField.vue'
import EmptyState from '@/components/EmptyState.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'
import StatusPill from '@/components/StatusPill.vue'
import UnitPhotos from '@/components/UnitPhotos.vue'
import RowAction from '@/components/RowAction.vue'
import AppIcon from '@/components/AppIcon.vue'

const route = useRoute()
const toast = useToastStore()
const { t } = useI18n()
const { items, meta, loading, filters, load, goToPage } = useCollection(units.list, {
  search: '',
  status: '',
  property_id: route.query.property_id ? Number(route.query.property_id) : '',
})

const STATUSES = ['vacant', 'occupied', 'maintenance']
const propertyOptions = ref([])

const editing = ref(null)
const removing = ref(null)
const busy = ref(false)
const errors = ref({})
const form = reactive({
  property_id: '',
  label: '',
  bedrooms: 1,
  bathrooms: 1,
  size_sqm: '',
  rent_amount: '',
  deposit_amount: '',
  status: 'vacant',
  description: '',
})

async function loadProperties() {
  const { data } = await properties.list({ per_page: 100 })
  propertyOptions.value = data.data ?? []
}

function openCreate() {
  editing.value = { id: null }
  errors.value = {}
  Object.assign(form, {
    property_id: filters.property_id || propertyOptions.value[0]?.id || '',
    label: '',
    bedrooms: 1,
    bathrooms: 1,
    size_sqm: '',
    rent_amount: '',
    deposit_amount: '',
    status: 'vacant',
    description: '',
  })
}

function openEdit(unit) {
  editing.value = unit
  errors.value = {}
  Object.assign(form, {
    property_id: unit.property_id,
    label: unit.label,
    bedrooms: unit.bedrooms,
    bathrooms: unit.bathrooms,
    size_sqm: unit.size_sqm ?? '',
    rent_amount: unit.rent_amount,
    deposit_amount: unit.deposit_amount,
    status: unit.status,
    description: unit.description ?? '',
  })
}

async function save() {
  busy.value = true
  errors.value = {}
  try {
    const payload = { ...form, size_sqm: form.size_sqm === '' ? null : form.size_sqm }
    if (editing.value.id) {
      await units.update(editing.value.id, payload)
      toast.success(t('units.updated'))
    } else {
      await units.create(payload)
      toast.success(t('units.added'))
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

async function remove() {
  busy.value = true
  try {
    await units.remove(removing.value.id)
    toast.success(t('units.deleted'))
    removing.value = null
    await load()
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  await loadProperties()
  await load()
})
</script>

<template>
  <div class="stack">
    <PageHeader :title="t('units.title')" :eyebrow="t('nav.portfolio')" :count="meta?.total ?? null">
      <template #actions>
        <button class="btn" type="button" :disabled="!propertyOptions.length" @click="openCreate"><AppIcon name="plus" :size="15" />{{ t('units.add') }}</button>
      </template>
    </PageHeader>

    <section class="card">
      <div class="filters">
        <div class="field" style="flex: 1">
          <label for="search">{{ t('common.search') }}</label>
          <input id="search" v-model="filters.search" class="input" :placeholder="t('units.searchHint')" />
        </div>
        <div class="field">
          <label for="property">{{ t('units.property') }}</label>
          <select id="property" v-model="filters.property_id" class="select">
            <option value="">{{ t('units.allProperties') }}</option>
            <option v-for="p in propertyOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div class="field">
          <label for="status">{{ t('common.status') }}</label>
          <select id="status" v-model="filters.status" class="select">
            <option value="">{{ t('common.anyStatus') }}</option>
            <option v-for="s in STATUSES" :key="s" :value="s">{{ t(`status.${s}`) }}</option>
          </select>
        </div>
      </div>

      <SkeletonTable v-if="loading" :columns="6" />

      <template v-else>
        <div class="table-scroll" v-if="items.length">
          <table class="table">
            <thead>
              <tr>
                <th>{{ t('units.title') }}</th>
                <th>{{ t('units.property') }}</th>
                <th>{{ t('units.bedsBaths') }}</th>
                <th>{{ t('common.status') }}</th>
                <th>{{ t('units.tenant') }}</th>
                <th class="right">{{ t('units.rent') }}</th>
                <th class="right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="unit in items" :key="unit.id">
                <td class="label">
                <span class="unit-line">
                  <img v-if="unit.cover_photo" :src="unit.cover_photo" class="thumb" alt="" loading="lazy" />
                  <span v-else class="thumb placeholder" />
                  {{ unit.label }}
                </span>
              </td>
                <td class="muted">{{ unit.property?.name ?? '—' }}</td>
                <td class="num muted">{{ unit.bedrooms }} / {{ unit.bathrooms }}</td>
                <td><StatusPill :status="unit.status" /></td>
                <td>
                  <RouterLink
                    v-if="unit.active_lease"
                    :to="`/leases/${unit.active_lease.id}`"
                    class="strong-link"
                  >
                    {{ unit.active_lease.tenant?.full_name ?? t('invoices.viewLease') }}
                  </RouterLink>
                  <span v-else class="muted">—</span>
                </td>
                <td class="right num">{{ formatMoney(unit.rent_amount) }}</td>
                <td class="actions">
                  <RowAction icon="edit" :label="t('common.edit')" @click="openEdit(unit)" />
                  <RowAction icon="trash" tone="danger" :label="t('common.delete')" @click="removing = unit" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState
          v-else-if="propertyOptions.length"
          :title="t('units.empty')"
          :hint="t('units.emptyHint')"
        >
          <button class="btn" type="button" @click="openCreate"><AppIcon name="plus" :size="15" />{{ t('units.add') }}</button>
        </EmptyState>

        <EmptyState v-else :title="t('units.noProperty')" :hint="t('units.noPropertyHint')">
          <RouterLink to="/properties" class="btn">{{ t('units.goToProperties') }}</RouterLink>
        </EmptyState>
      </template>

      <PagerBar :meta="meta" @page="goToPage" />
    </section>

    <ModalDialog
      v-if="editing"
      :title="editing.id ? t('units.edit') : t('units.add')"
      @close="editing = null"
    >
      <form class="stack-form" @submit.prevent="save">
        <FormField :label="t('units.property')" name="property_id" :errors="errors">
          <select v-model="form.property_id" class="select" required>
            <option v-for="p in propertyOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </FormField>
        <FormField
          v-slot="{ invalid }"
          :label="t('units.label')"
          name="label"
          :errors="errors"
          :hint="t('units.labelHint')"
        >
          <input v-model="form.label" class="input" :class="{ 'is-invalid': invalid }" required />
        </FormField>
        <div class="grid-2">
          <FormField :label="t('units.bedrooms')" name="bedrooms" :errors="errors">
            <input v-model.number="form.bedrooms" class="input num" type="number" min="0" />
          </FormField>
          <FormField :label="t('units.bathrooms')" name="bathrooms" :errors="errors">
            <input v-model.number="form.bathrooms" class="input num" type="number" min="0" />
          </FormField>
        </div>
        <div class="grid-2">
          <FormField v-slot="{ invalid }" :label="t('units.monthlyRent')" name="rent_amount" :errors="errors">
            <input
              v-model="form.rent_amount"
              class="input num"
              :class="{ 'is-invalid': invalid }"
              type="number"
              step="0.01"
              min="0"
              required
            />
          </FormField>
          <FormField :label="t('units.deposit')" name="deposit_amount" :errors="errors">
            <input v-model="form.deposit_amount" class="input num" type="number" step="0.01" min="0" />
          </FormField>
        </div>
        <div class="grid-2">
          <FormField :label="t('units.size')" name="size_sqm" :errors="errors">
            <input v-model="form.size_sqm" class="input num" type="number" step="0.01" min="0" />
          </FormField>
          <FormField
            :label="t('common.status')"
            name="status"
            :errors="errors"
            :hint="t('units.statusHint')"
          >
            <select v-model="form.status" class="select">
              <option v-for="s in STATUSES" :key="s" :value="s">{{ t(`status.${s}`) }}</option>
            </select>
          </FormField>
        </div>
        <FormField :label="t('common.notes')" name="description" :errors="errors">
          <textarea v-model="form.description" class="textarea" />
        </FormField>

        <!-- Photos need a saved unit to belong to. -->
        <UnitPhotos v-if="editing.id" :unit-id="editing.id" />
        <p v-else class="muted hint-save">{{ t('photos.saveFirst') }}</p>
      </form>
      <template #actions>
        <button class="btn btn-ghost" type="button" @click="editing = null">
          {{ t('common.cancel') }}
        </button>
        <button class="btn" type="button" :disabled="busy" @click="save">
          {{ busy ? t('common.saving') : editing.id ? t('common.save') : t('units.add') }}
        </button>
      </template>
    </ModalDialog>

    <ConfirmDialog
      v-if="removing"
      :title="t('units.deleteTitle')"
      :message="t('units.deleteMessage', { label: removing.label })"
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
.label { font-weight: 500; }
.unit-line { display: inline-flex; align-items: center; gap: 9px; }
.thumb {
  width: 34px;
  height: 26px;
  border-radius: 5px;
  object-fit: cover;
  flex: none;
  border: 1px solid var(--line);
}
.thumb.placeholder { background: var(--surface-3); border-style: dashed; }
.stack-form { display: flex; flex-direction: column; gap: var(--sp-3); }
.hint-save { margin: 0; font-size: var(--text-sm); }
</style>
