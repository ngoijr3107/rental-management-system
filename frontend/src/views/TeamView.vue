<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { team } from '@/api/resources'
import { errorMessage, fieldErrors } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { formatDate } from '@/composables/format'
import PageHeader from '@/components/PageHeader.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import FormField from '@/components/FormField.vue'
import StatusPill from '@/components/StatusPill.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'
import RowAction from '@/components/RowAction.vue'
import AppIcon from '@/components/AppIcon.vue'

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToastStore()

const members = ref([])
const roles = ref([])
const loading = ref(true)
const editing = ref(null)
const removing = ref(null)
const busy = ref(false)
const errors = ref({})

/* Roles come from the API rather than a list kept here, so a role added on
   the server appears in this form without a frontend change. */
const assignable = computed(() =>
  roles.value.filter((role) => role.assignable).map((role) => role.value),
)

const blank = {
  name: '',
  email: '',
  phone: '',
  role: 'agent',
  password: '',
  password_confirmation: '',
  is_active: true,
}
const form = reactive({ ...blank })

async function load() {
  loading.value = true
  try {
    const [list, roleList] = await Promise.all([team.list(), team.roles()])
    members.value = list.data.data ?? []
    roles.value = roleList.data.data ?? []
  } catch (error) {
    toast.error(errorMessage(error, t('team.loadFailed')))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = { id: null }
  errors.value = {}
  Object.assign(form, blank, { role: assignable.value[0] ?? 'agent' })
}

function openEdit(member) {
  editing.value = member
  errors.value = {}
  Object.assign(form, {
    ...blank,
    name: member.name,
    email: member.email,
    phone: member.phone ?? '',
    role: member.role,
    is_active: member.is_active,
  })
}

async function save() {
  busy.value = true
  errors.value = {}
  try {
    if (editing.value.id) {
      const payload = {
        name: form.name,
        phone: form.phone,
        role: form.role,
        is_active: form.is_active,
      }
      // Only send a password when one was actually typed.
      if (form.password) {
        payload.password = form.password
        payload.password_confirmation = form.password_confirmation
      }
      await team.update(editing.value.id, payload)
      toast.success(t('team.updated'))
    } else {
      await team.create({ ...form })
      toast.success(t('team.added'))
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
    await team.remove(removing.value.id)
    toast.success(t('team.removed'))
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
    <PageHeader :title="t('team.title')" :eyebrow="t('nav.admin')" :count="members.length || null">
      <template #actions>
        <button v-if="auth.can('users.manage')" class="btn" type="button" @click="openCreate"><AppIcon name="plus" :size="15" />{{ t('team.add') }}</button>
      </template>
    </PageHeader>

    <section class="card">
      <SkeletonTable v-if="loading" :columns="5" />

      <div class="table-scroll" v-else>
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('common.name') }}</th>
              <th>{{ t('common.email') }}</th>
              <th>{{ t('team.role') }}</th>
              <th>{{ t('common.status') }}</th>
              <th>{{ t('team.lastSeen') }}</th>
              <th class="right">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="member in members" :key="member.id">
              <td>
                <span class="name">{{ member.name }}</span>
                <span v-if="member.is_you" class="you">{{ t('team.you') }}</span>
              </td>
              <td class="muted">{{ member.email }}</td>
              <td>
                <StatusPill :status="member.role" :tone="member.is_owner ? 'accent' : 'neutral'" />
              </td>
              <td>
                <StatusPill :status="member.is_active ? 'active' : 'suspended'" />
              </td>
              <td class="num muted">
                {{ member.last_login_at ? formatDate(member.last_login_at) : t('team.never') }}
              </td>
              <td class="actions">
                <template v-if="auth.can('users.manage') && !member.is_owner">
                  <RowAction icon="edit" :label="t('common.edit')" @click="openEdit(member)" />
                  <RowAction v-if="!member.is_you" icon="ban" tone="danger" :label="t('team.remove')" @click="removing = member" />
                </template>
                <span v-else class="muted">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- What each role can actually do, so the choice is informed -->
    <section v-if="roles.length" class="card">
      <div class="card-head"><h3>{{ t('team.whatRolesDo') }}</h3></div>
      <div class="table-scroll">
        <table class="table">
          <thead>
            <tr>
              <th>{{ t('team.role') }}</th>
              <th>{{ t('team.allows') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="role in roles" :key="role.value">
              <td><StatusPill :status="role.value" :tone="role.value === 'owner' ? 'accent' : 'neutral'" /></td>
              <td class="muted">{{ t(`team.describe.${role.value}`) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <ModalDialog
      v-if="editing"
      :title="editing.id ? t('team.editTitle') : t('team.add')"
      :subtitle="editing.id ? '' : t('team.addSubtitle')"
      @close="editing = null"
    >
      <form class="stack-form" @submit.prevent="save">
        <FormField v-slot="{ invalid }" :label="t('common.name')" name="name" :errors="errors">
          <input v-model="form.name" class="input" :class="{ 'is-invalid': invalid }" required />
        </FormField>

        <FormField
          v-if="!editing.id"
          v-slot="{ invalid }"
          :label="t('common.email')"
          name="email"
          :errors="errors"
        >
          <input v-model="form.email" class="input" :class="{ 'is-invalid': invalid }" type="email" required />
        </FormField>

        <FormField :label="t('common.phone')" name="phone" :errors="errors">
          <input v-model="form.phone" class="input" />
        </FormField>

        <FormField
          :label="t('team.role')"
          name="role"
          :errors="errors"
          :hint="t(`team.describe.${form.role}`)"
        >
          <select v-model="form.role" class="select">
            <option v-for="role in assignable" :key="role" :value="role">{{ t(`status.${role}`) }}</option>
          </select>
        </FormField>

        <FormField
          v-slot="{ invalid }"
          :label="editing.id ? t('team.newPassword') : t('auth.password')"
          name="password"
          :errors="errors"
          :hint="editing.id ? t('team.passwordOptional') : t('team.passwordHint')"
        >
          <input
            v-model="form.password"
            class="input"
            :class="{ 'is-invalid': invalid }"
            type="password"
            :required="!editing.id"
          />
        </FormField>

        <FormField v-if="form.password" :label="t('auth.confirmPassword')" name="password_confirmation" :errors="errors">
          <input v-model="form.password_confirmation" class="input" type="password" />
        </FormField>

        <label v-if="editing.id" class="check">
          <input v-model="form.is_active" type="checkbox" />
          <span>
            {{ t('team.activeLabel') }}
            <em class="muted">{{ t('team.activeHint') }}</em>
          </span>
        </label>
      </form>

      <template #actions>
        <button class="btn btn-ghost" type="button" @click="editing = null">{{ t('common.cancel') }}</button>
        <button class="btn" type="button" :disabled="busy" @click="save">
          {{ busy ? t('common.saving') : editing.id ? t('common.save') : t('team.add') }}
        </button>
      </template>
    </ModalDialog>

    <ConfirmDialog
      v-if="removing"
      :title="t('team.removeTitle')"
      :message="t('team.removeMessage', { name: removing.name })"
      :confirm-label="t('team.remove')"
      danger
      :busy="busy"
      @close="removing = null"
      @confirm="remove"
    />
  </div>
</template>

<style scoped>
.name { font-weight: 500; }
.you {
  margin-left: 6px;
  padding: 1px 6px;
  border-radius: 4px;
  background: var(--accent-wash);
  color: var(--accent-ink);
  font-size: var(--text-xs);
}
.nowrap { white-space: nowrap; }
.stack-form { display: flex; flex-direction: column; gap: var(--sp-3); }
.check { display: flex; gap: 9px; align-items: flex-start; font-size: var(--text-base); }
.check em { display: block; font-style: normal; font-size: var(--text-sm); }
</style>
