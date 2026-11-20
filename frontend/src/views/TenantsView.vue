<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { tenants } from '@/api/resources'
import { errorMessage, fieldErrors } from '@/api/client'
import { useCollection } from '@/composables/useCollection'
import { useToastStore } from '@/stores/toast'
import PageHeader from '@/components/PageHeader.vue'
import PagerBar from '@/components/PagerBar.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import FormField from '@/components/FormField.vue'
import EmptyState from '@/components/EmptyState.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'
import RowAction from '@/components/RowAction.vue'
import AppIcon from '@/components/AppIcon.vue'

const toast = useToastStore()
const { t } = useI18n()
const { items, meta, loading, filters, load, goToPage } = useCollection(tenants.list, { search: '' })

const editing = ref(null)
const removing = ref(null)
const busy = ref(false)
const errors = ref({})
const blank = {
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  national_id: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  notes: '',
}
const form = reactive({ ...blank })

function openCreate() {
  editing.value = { id: null }
  errors.value = {}
  Object.assign(form, blank)
}

function openEdit(tenant) {
  editing.value = tenant
  errors.value = {}
  Object.assign(form, {
    first_name: tenant.first_name,
    last_name: tenant.last_name,
    email: tenant.email ?? '',
    phone: tenant.phone,
    national_id: tenant.national_id ?? '',
    emergency_contact_name: tenant.emergency_contact_name ?? '',
    emergency_contact_phone: tenant.emergency_contact_phone ?? '',
    notes: tenant.notes ?? '',
  })
}

async function save() {
  busy.value = true
  errors.value = {}
  try {
    if (editing.value.id) {
      await tenants.update(editing.value.id, { ...form })
      toast.success(t('tenants.updated'))
    } else {
      await tenants.create({ ...form })
      toast.success(t('tenants.added'))
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
    await tenants.remove(removing.value.id)
    toast.success(t('tenants.deleted'))
    removing.value = null
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
    <PageHeader :title="t('tenants.title')" :eyebrow="t('nav.letting')" :count="meta?.total ?? null">
      <template #actions>
        <button class="btn" type="button" @click="openCreate"><AppIcon name="plus" :size="15" />{{ t('tenants.add') }}</button>
      </template>
    </PageHeader>

    <section class="card">
      <div class="filters">
        <div class="field" style="flex: 1">
          <label for="search">{{ t('common.search') }}</label>
          <input id="search" v-model="filters.search" class="input" :placeholder="t('tenants.searchHint')" />
        </div>
      </div>

      <SkeletonTable v-if="loading" :columns="4" />

      <template v-else>
        <div class="table-scroll" v-if="items.length">
          <table class="table">
            <thead>
              <tr>
                <th>{{ t('common.name') }}</th>
                <th>{{ t('common.phone') }}</th>
                <th>{{ t('common.email') }}</th>
                <th>{{ t('tenants.nationalId') }}</th>
                <th class="right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="tenant in items" :key="tenant.id">
                <td class="name">{{ tenant.full_name }}</td>
                <td class="num">{{ tenant.phone }}</td>
                <td class="muted">{{ tenant.email || '—' }}</td>
                <td class="num muted">{{ tenant.national_id || '—' }}</td>
                <td class="actions">
                  <RowAction icon="edit" :label="t('common.edit')" @click="openEdit(tenant)" />
                  <RowAction icon="trash" tone="danger" :label="t('common.delete')" @click="removing = tenant" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState v-else :title="t('tenants.empty')" :hint="t('tenants.emptyHint')">
          <button class="btn" type="button" @click="openCreate"><AppIcon name="plus" :size="15" />{{ t('tenants.add') }}</button>
        </EmptyState>
      </template>

      <PagerBar :meta="meta" @page="goToPage" />
    </section>

    <ModalDialog
      v-if="editing"
      :title="editing.id ? t('tenants.edit') : t('tenants.add')"
      @close="editing = null"
    >
      <form class="stack-form" @submit.prevent="save">
        <div class="grid-2">
          <FormField v-slot="{ invalid }" :label="t('tenants.firstName')" name="first_name" :errors="errors">
            <input v-model="form.first_name" class="input" :class="{ 'is-invalid': invalid }" required />
          </FormField>
          <FormField v-slot="{ invalid }" :label="t('tenants.lastName')" name="last_name" :errors="errors">
            <input v-model="form.last_name" class="input" :class="{ 'is-invalid': invalid }" required />
          </FormField>
        </div>
        <div class="grid-2">
          <FormField v-slot="{ invalid }" :label="t('common.phone')" name="phone" :errors="errors">
            <input v-model="form.phone" class="input" :class="{ 'is-invalid': invalid }" required />
          </FormField>
          <FormField :label="t('common.email')" name="email" :errors="errors">
            <input v-model="form.email" class="input" type="email" />
          </FormField>
        </div>
        <FormField :label="t('tenants.nationalId')" name="national_id" :errors="errors">
          <input v-model="form.national_id" class="input" />
        </FormField>
        <div class="grid-2">
          <FormField :label="t('tenants.emergencyContact')" name="emergency_contact_name" :errors="errors">
            <input v-model="form.emergency_contact_name" class="input" />
          </FormField>
          <FormField :label="t('tenants.emergencyPhone')" name="emergency_contact_phone" :errors="errors">
            <input v-model="form.emergency_contact_phone" class="input" />
          </FormField>
        </div>
        <FormField :label="t('common.notes')" name="notes" :errors="errors">
          <textarea v-model="form.notes" class="textarea" />
        </FormField>
      </form>
      <template #actions>
        <button class="btn btn-ghost" type="button" @click="editing = null">
          {{ t('common.cancel') }}
        </button>
        <button class="btn" type="button" :disabled="busy" @click="save">
          {{ busy ? t('common.saving') : editing.id ? t('common.save') : t('tenants.add') }}
        </button>
      </template>
    </ModalDialog>

    <ConfirmDialog
      v-if="removing"
      :title="t('tenants.deleteTitle')"
      :message="t('tenants.deleteMessage', { name: removing.full_name })"
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
.name { font-weight: 500; }
.stack-form { display: flex; flex-direction: column; gap: var(--sp-3); }
</style>
