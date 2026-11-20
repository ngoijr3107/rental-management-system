<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { settings as settingsApi } from '@/api/resources'
import { errorMessage, fieldErrors } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { usePrefsStore } from '@/stores/prefs'
import { useToastStore } from '@/stores/toast'
import PageHeader from '@/components/PageHeader.vue'
import FormField from '@/components/FormField.vue'
import BillingPeriodPicker from '@/components/BillingPeriodPicker.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'
import BrandMark from '@/components/BrandMark.vue'

const { t, locale } = useI18n()
const auth = useAuthStore()
const prefs = usePrefsStore()
const toast = useToastStore()

const loading = ref(true)
const busy = ref(false)
const errors = ref({})

const form = reactive({
  business_name: '',
  business_email: '',
  business_phone: '',
  business_address: '',
  currency: 'USD',
  locale: 'en',
  invoice_prefix: 'INV',
  default_billing_day: 1,
  auto_invoice: true,
  collect_deposit: true,
  notify_tenants: true,
  remind_days_before: 3,
  grace_days: 0,
  default_billing_period_months: 1,
})

const editable = ref(false)
const logoInput = ref(null)
const uploading = ref(false)
const removingLogo = ref(false)

async function pickLogo(event) {
  const file = event.target.files?.[0]
  if (!file) return

  uploading.value = true
  errors.value = {}
  try {
    const { data } = await settingsApi.uploadLogo(file)
    auth.settings = data.data
    Object.assign(form, data.data)
    toast.success(t('settings.logoUpdated'))
  } catch (error) {
    errors.value = fieldErrors(error)
    toast.error(errorMessage(error, t('settings.logoFailed')))
  } finally {
    uploading.value = false
    // Reset so choosing the same file again still fires a change event.
    if (logoInput.value) logoInput.value.value = ''
  }
}

async function removeLogo() {
  removingLogo.value = true
  try {
    const { data } = await settingsApi.deleteLogo()
    auth.settings = data.data
    Object.assign(form, data.data)
    toast.success(t('settings.logoRemoved'))
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    removingLogo.value = false
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await settingsApi.show()
    Object.assign(form, data.data)
    auth.settings = data.data
    editable.value = auth.can('settings.manage')
  } catch (error) {
    toast.error(errorMessage(error, t('settings.loadFailed')))
  } finally {
    loading.value = false
  }
}

async function save() {
  busy.value = true
  errors.value = {}
  try {
    const { data } = await settingsApi.update({ ...form })
    auth.settings = data.data
    Object.assign(form, data.data)

    // The account's default language becomes this device's language too.
    if (data.data.locale && data.data.locale !== prefs.locale) {
      locale.value = data.data.locale
      prefs.setLocale(data.data.locale)
    }

    toast.success(t('settings.saved'))
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
    <PageHeader :title="t('settings.title')" :eyebrow="t('nav.admin')">
      <template #actions>
        <button v-if="editable" class="btn" type="button" :disabled="busy" @click="save">
          {{ busy ? t('common.saving') : t('common.save') }}
        </button>
      </template>
    </PageHeader>

    <p v-if="!loading && !editable" class="card notice">{{ t('settings.readOnly') }}</p>

    <section v-if="loading" class="card"><SkeletonTable :rows="5" :columns="3" /></section>

    <template v-else>
      <section class="card">
        <div class="card-head">
          <div>
            <h3>{{ t('settings.branding') }}</h3>
            <p class="muted sub">{{ t('settings.brandingHint') }}</p>
          </div>
        </div>
        <div class="body brand-row">
          <div class="preview">
            <BrandMark :size="56" :show-name="false" />
            <div class="preview-text">
              <p class="brand-name">{{ auth.brandName || t('app.name') }}</p>
              <p class="muted small">
                {{ auth.logoUrl ? t('settings.logoInUse') : t('settings.noLogo') }}
              </p>
            </div>
          </div>

          <div v-if="editable" class="brand-actions">
            <input
              ref="logoInput"
              class="hidden-input"
              type="file"
              accept="image/png,image/jpeg,image/webp"
              @change="pickLogo"
            />
            <button class="btn btn-ghost" type="button" :disabled="uploading" @click="logoInput?.click()">
              {{ uploading ? t('settings.uploading') : t('settings.chooseLogo') }}
            </button>
            <button
              v-if="auth.logoUrl"
              class="btn btn-quiet"
              type="button"
              :disabled="removingLogo"
              @click="removeLogo"
            >
              {{ t('settings.removeLogo') }}
            </button>
            <p class="muted small rules">{{ t('settings.logoRules') }}</p>
            <p v-if="errors.logo" class="field-error">{{ errors.logo[0] }}</p>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="card-head">
          <div>
            <h3>{{ t('settings.business') }}</h3>
            <p class="muted sub">{{ t('settings.businessHint') }}</p>
          </div>
        </div>
        <div class="body grid-2">
          <FormField
            :label="t('settings.businessName')"
            name="business_name"
            :errors="errors"
            :hint="t('settings.businessNameHint')"
          >
            <input v-model="form.business_name" class="input" :disabled="!editable" />
          </FormField>
          <FormField :label="t('settings.businessPhone')" name="business_phone" :errors="errors">
            <input v-model="form.business_phone" class="input" :disabled="!editable" />
          </FormField>
          <FormField :label="t('settings.businessEmail')" name="business_email" :errors="errors">
            <input v-model="form.business_email" class="input" type="email" :disabled="!editable" />
          </FormField>
          <FormField :label="t('settings.businessAddress')" name="business_address" :errors="errors">
            <textarea v-model="form.business_address" class="textarea" :disabled="!editable" />
          </FormField>
        </div>
      </section>

      <section class="card">
        <div class="card-head">
          <div>
            <h3>{{ t('settings.money') }}</h3>
            <p class="muted sub">{{ t('settings.moneyHint') }}</p>
          </div>
        </div>
        <div class="body grid-2">
          <FormField
            :label="t('settings.currency')"
            name="currency"
            :errors="errors"
            :hint="t('settings.currencyHint')"
          >
            <input
              v-model="form.currency"
              class="input num"
              maxlength="3"
              style="text-transform: uppercase"
              :disabled="!editable"
            />
          </FormField>

          <FormField
            :label="t('settings.invoicePrefix')"
            name="invoice_prefix"
            :errors="errors"
            :hint="t('settings.invoicePrefixHint', { example: `${form.invoice_prefix || 'INV'}-202601-0001` })"
          >
            <input v-model="form.invoice_prefix" class="input num" maxlength="8" :disabled="!editable" />
          </FormField>

          <FormField
            :label="t('settings.graceDays')"
            name="grace_days"
            :errors="errors"
            :hint="t('settings.graceDaysHint')"
          >
            <input
              v-model.number="form.grace_days"
              class="input num"
              type="number"
              min="0"
              max="31"
              :disabled="!editable"
            />
          </FormField>

          <FormField
            :label="t('settings.defaultTerm')"
            name="default_billing_period_months"
            :errors="errors"
            :hint="t('settings.defaultTermHint')"
          >
            <BillingPeriodPicker
              v-model="form.default_billing_period_months"
              :disabled="!editable"
            />
          </FormField>

          <div class="field">
            <label>{{ t('settings.deposit') }}</label>
            <label class="check">
              <input v-model="form.collect_deposit" type="checkbox" :disabled="!editable" />
              <span>
                {{ t('settings.depositLabel') }}
                <em class="muted">{{ t('settings.depositHint') }}</em>
              </span>
            </label>
          </div>

          <div class="field">
            <label>{{ t('settings.autoInvoice') }}</label>
            <label class="check">
              <input v-model="form.auto_invoice" type="checkbox" :disabled="!editable" />
              <span>
                {{ t('settings.autoInvoiceLabel') }}
                <em class="muted">{{ t('settings.autoInvoiceHint') }}</em>
              </span>
            </label>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="card-head">
          <div>
            <h3>{{ t('settings.notifications') }}</h3>
            <p class="muted sub">{{ t('settings.notifyTenantsHint') }}</p>
          </div>
        </div>
        <div class="body grid-2">
          <div class="field">
            <label>{{ t('settings.notifications') }}</label>
            <label class="check">
              <input v-model="form.notify_tenants" type="checkbox" :disabled="!editable" />
              <span>{{ t('settings.notifyTenants') }}</span>
            </label>
          </div>
          <FormField :label="t('settings.remindDays')" name="remind_days_before" :errors="errors">
            <input
              v-model.number="form.remind_days_before"
              class="input num"
              type="number"
              min="0"
              max="30"
              :disabled="!editable"
            />
          </FormField>
        </div>
      </section>

      <section class="card">
        <div class="card-head">
          <div>
            <h3>{{ t('settings.regional') }}</h3>
            <p class="muted sub">{{ t('settings.regionalHint') }}</p>
          </div>
        </div>
        <div class="body grid-2">
          <FormField :label="t('settings.defaultLanguage')" name="locale" :errors="errors">
            <select v-model="form.locale" class="select" :disabled="!editable">
              <option value="en">{{ t('prefs.english') }}</option>
              <option value="sw">{{ t('prefs.swahili') }}</option>
            </select>
          </FormField>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.body { padding: var(--sp-5); }
.sub { margin: 2px 0 0; font-size: var(--text-sm); }
.notice { padding: var(--sp-4) var(--sp-5); margin: 0; color: var(--text-muted); }
.brand-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-5);
}
.preview { display: flex; align-items: center; gap: var(--sp-4); }
.brand-name { margin: 0; font-family: var(--display); font-size: var(--text-lg); font-weight: 600; }
.preview-text .small { margin: 2px 0 0; }
.small { font-size: var(--text-sm); }
.brand-actions { display: flex; flex-wrap: wrap; align-items: center; gap: var(--sp-2); }
.rules { flex-basis: 100%; margin: 0; }
.hidden-input { display: none; }
.check { display: flex; gap: 9px; align-items: flex-start; font-size: var(--text-base); }
.check em { display: block; font-style: normal; font-size: var(--text-sm); }
</style>
