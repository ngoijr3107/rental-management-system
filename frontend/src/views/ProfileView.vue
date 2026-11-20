<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { profile } from '@/api/resources'
import { errorMessage, fieldErrors } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { formatDate } from '@/composables/format'
import PageHeader from '@/components/PageHeader.vue'
import FormField from '@/components/FormField.vue'
import StatusPill from '@/components/StatusPill.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToastStore()

const loading = ref(true)
const savingDetails = ref(false)
const savingPassword = ref(false)
const detailErrors = ref({})
const passwordErrors = ref({})

const details = reactive({ name: '', email: '', phone: '' })
const secret = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

async function load() {
  loading.value = true
  try {
    const { data } = await profile.show()
    Object.assign(details, {
      name: data.data.name ?? '',
      email: data.data.email ?? '',
      phone: data.data.phone ?? '',
    })
    auth.user = data.data
  } catch (error) {
    toast.error(errorMessage(error, t('profile.loadFailed')))
  } finally {
    loading.value = false
  }
}

async function saveDetails() {
  savingDetails.value = true
  detailErrors.value = {}
  try {
    const { data } = await profile.update({ ...details })
    auth.user = data.data
    toast.success(t('profile.saved'))
  } catch (error) {
    detailErrors.value = fieldErrors(error)
    if (!Object.keys(detailErrors.value).length) toast.error(errorMessage(error))
  } finally {
    savingDetails.value = false
  }
}

async function savePassword() {
  savingPassword.value = true
  passwordErrors.value = {}
  try {
    await profile.changePassword({ ...secret })
    Object.assign(secret, {
      current_password: '',
      password: '',
      password_confirmation: '',
    })
    toast.success(t('profile.passwordChanged'))
  } catch (error) {
    passwordErrors.value = fieldErrors(error)
    if (!Object.keys(passwordErrors.value).length) toast.error(errorMessage(error))
  } finally {
    savingPassword.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="stack">
    <PageHeader :title="t('profile.title')" :eyebrow="t('nav.account')" />

    <section v-if="loading" class="card"><SkeletonTable :rows="4" :columns="2" /></section>

    <template v-else>
      <section class="card">
        <div class="card-head">
          <div>
            <h3>{{ t('profile.details') }}</h3>
            <p class="muted sub">{{ t('profile.detailsHint') }}</p>
          </div>
          <div class="who">
            <StatusPill
              v-if="auth.user?.role"
              :status="auth.user.role"
              :tone="auth.user.is_owner ? 'accent' : 'neutral'"
            />
          </div>
        </div>

        <form class="body" @submit.prevent="saveDetails">
          <div class="grid-2">
            <FormField v-slot="{ invalid }" :label="t('common.name')" name="name" :errors="detailErrors">
              <input v-model="details.name" class="input" :class="{ 'is-invalid': invalid }" required />
            </FormField>
            <FormField :label="t('common.phone')" name="phone" :errors="detailErrors">
              <input v-model="details.phone" class="input" />
            </FormField>
          </div>

          <FormField
            v-slot="{ invalid }"
            :label="t('common.email')"
            name="email"
            :errors="detailErrors"
            :hint="t('profile.emailHint')"
          >
            <input
              v-model="details.email"
              class="input"
              :class="{ 'is-invalid': invalid }"
              type="email"
              required
            />
          </FormField>

          <p v-if="auth.user?.last_login_at" class="muted stamp">
            {{ t('profile.lastSignedIn', { at: formatDate(auth.user.last_login_at) }) }}
          </p>

          <div class="actions">
            <button class="btn" type="submit" :disabled="savingDetails">
              {{ savingDetails ? t('common.saving') : t('common.save') }}
            </button>
          </div>
        </form>
      </section>

      <section class="card">
        <div class="card-head">
          <div>
            <h3>{{ t('profile.password') }}</h3>
            <p class="muted sub">{{ t('profile.passwordHint') }}</p>
          </div>
        </div>

        <form class="body" @submit.prevent="savePassword">
          <FormField
            v-slot="{ invalid }"
            :label="t('profile.currentPassword')"
            name="current_password"
            :errors="passwordErrors"
          >
            <input
              v-model="secret.current_password"
              class="input"
              :class="{ 'is-invalid': invalid }"
              type="password"
              autocomplete="current-password"
              required
            />
          </FormField>

          <div class="grid-2">
            <FormField
              v-slot="{ invalid }"
              :label="t('profile.newPassword')"
              name="password"
              :errors="passwordErrors"
              :hint="t('auth.passwordHint')"
            >
              <input
                v-model="secret.password"
                class="input"
                :class="{ 'is-invalid': invalid }"
                type="password"
                autocomplete="new-password"
                required
              />
            </FormField>

            <FormField
              :label="t('auth.confirmPassword')"
              name="password_confirmation"
              :errors="passwordErrors"
            >
              <input
                v-model="secret.password_confirmation"
                class="input"
                type="password"
                autocomplete="new-password"
                required
              />
            </FormField>
          </div>

          <div class="actions">
            <button class="btn" type="submit" :disabled="savingPassword">
              {{ savingPassword ? t('common.saving') : t('profile.changePassword') }}
            </button>
          </div>
        </form>
      </section>
    </template>
  </div>
</template>

<style scoped>
.body { padding: var(--sp-5); display: flex; flex-direction: column; gap: var(--sp-4); }
.sub { margin: 2px 0 0; font-size: var(--text-sm); }
.stamp { margin: 0; font-size: var(--text-sm); }
.actions { display: flex; justify-content: flex-end; }
.who { display: flex; align-items: center; gap: var(--sp-2); }

@media (max-width: 620px) {
  .actions .btn { width: 100%; }
}
</style>
