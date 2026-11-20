<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { properties } from '@/api/resources'
import { errorMessage, fieldErrors } from '@/api/client'
import { useCollection } from '@/composables/useCollection'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
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
const auth = useAuthStore()
const { t } = useI18n()
const { items, meta, loading, filters, load, goToPage } = useCollection(properties.list, {
  search: '',
  type: '',
})

const TYPES = ['apartment', 'house', 'commercial', 'mixed']

const editing = ref(null)
const removing = ref(null)
const busy = ref(false)
const errors = ref({})
const form = reactive({ name: '', type: 'apartment', address: '', city: '', description: '' })

function openCreate() {
  editing.value = { id: null }
  errors.value = {}
  Object.assign(form, { name: '', type: 'apartment', address: '', city: '', description: '' })
}

function openEdit(property) {
  editing.value = property
  errors.value = {}
  Object.assign(form, {
    name: property.name,
    type: property.type,
    address: property.address,
    city: property.city ?? '',
    description: property.description ?? '',
  })
}

async function save() {
  busy.value = true
  errors.value = {}
  try {
    if (editing.value.id) {
      await properties.update(editing.value.id, { ...form })
      toast.success(t('properties.updated'))
    } else {
      await properties.create({ ...form })
      toast.success(t('properties.added'))
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
    await properties.remove(removing.value.id)
    toast.success(t('properties.deleted'))
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
    <PageHeader
      :title="t('properties.title')"
      :eyebrow="t('nav.portfolio')"
      :count="meta?.total ?? null"
    >
      <template #actions>
        <button v-if="auth.can('properties.manage')" class="btn" type="button" @click="openCreate"><AppIcon name="plus" :size="15" />{{ t('properties.add') }}</button>
      </template>
    </PageHeader>

    <section class="card">
      <div class="filters">
        <div class="field" style="flex: 1">
          <label for="search">{{ t('common.search') }}</label>
          <input id="search" v-model="filters.search" class="input" :placeholder="t('properties.searchHint')" />
        </div>
        <div class="field">
          <label for="type">{{ t('common.type') }}</label>
          <select id="type" v-model="filters.type" class="select">
            <option value="">{{ t('properties.anyType') }}</option>
            <option v-for="type in TYPES" :key="type" :value="type">{{ t(`status.${type}`) }}</option>
          </select>
        </div>
      </div>

      <SkeletonTable v-if="loading" :columns="5" />

      <template v-else>
        <div class="table-scroll" v-if="items.length">
          <table class="table">
            <thead>
              <tr>
                <th>{{ t('common.name') }}</th>
                <th>{{ t('common.type') }}</th>
                <th>{{ t('properties.address') }}</th>
                <th class="right">{{ t('properties.unitsCount') }}</th>
                <th class="right">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="property in items" :key="property.id">
                <td>
                  <RouterLink :to="`/properties/${property.id}`" class="strong-link">
                    {{ property.name }}
                  </RouterLink>
                </td>
                <td><StatusPill :status="property.type" tone="neutral" /></td>
                <td class="muted">
                  {{ property.address }}<template v-if="property.city">, {{ property.city }}</template>
                </td>
                <td class="right num">{{ property.units_count ?? '—' }}</td>
                <td class="actions">
                  <template v-if="auth.can('properties.manage')">
                  <RowAction icon="edit" :label="t('common.edit')" @click="openEdit(property)" />
                  <RowAction icon="trash" tone="danger" :label="t('common.delete')" @click="removing = property" />
                  </template>
                  <span v-else class="muted">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState v-else :title="t('properties.empty')" :hint="t('properties.emptyHint')">
          <button class="btn" type="button" @click="openCreate"><AppIcon name="plus" :size="15" />{{ t('properties.add') }}</button>
        </EmptyState>
      </template>

      <PagerBar :meta="meta" @page="goToPage" />
    </section>

    <ModalDialog
      v-if="editing"
      :title="editing.id ? t('properties.edit') : t('properties.add')"
      @close="editing = null"
    >
      <form class="stack-form" @submit.prevent="save">
        <FormField v-slot="{ invalid }" :label="t('common.name')" name="name" :errors="errors">
          <input v-model="form.name" class="input" :class="{ 'is-invalid': invalid }" required />
        </FormField>
        <FormField :label="t('common.type')" name="type" :errors="errors">
          <select v-model="form.type" class="select">
            <option v-for="type in TYPES" :key="type" :value="type">{{ t(`status.${type}`) }}</option>
          </select>
        </FormField>
        <FormField v-slot="{ invalid }" :label="t('properties.address')" name="address" :errors="errors">
          <input v-model="form.address" class="input" :class="{ 'is-invalid': invalid }" required />
        </FormField>
        <FormField :label="t('properties.city')" name="city" :errors="errors">
          <input v-model="form.city" class="input" />
        </FormField>
        <FormField :label="t('common.notes')" name="description" :errors="errors">
          <textarea v-model="form.description" class="textarea" />
        </FormField>
      </form>
      <template #actions>
        <button class="btn btn-ghost" type="button" @click="editing = null">
          {{ t('common.cancel') }}
        </button>
        <button class="btn" type="button" :disabled="busy" @click="save">
          {{ busy ? t('common.saving') : editing.id ? t('common.save') : t('properties.add') }}
        </button>
      </template>
    </ModalDialog>

    <ConfirmDialog
      v-if="removing"
      :title="t('properties.deleteTitle')"
      :message="t('properties.deleteMessage', { name: removing.name })"
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
.stack-form { display: flex; flex-direction: column; gap: var(--sp-3); }
</style>
