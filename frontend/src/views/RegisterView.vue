<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { errorMessage, fieldErrors } from '@/api/client'
import FormField from '@/components/FormField.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import LanguageToggle from '@/components/LanguageToggle.vue'
import PaletteToggle from '@/components/PaletteToggle.vue'

const auth = useAuthStore()
const router = useRouter()
const { t } = useI18n()

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
})
const errors = ref({})
const problem = ref('')
const busy = ref(false)

async function submit() {
  busy.value = true
  errors.value = {}
  problem.value = ''
  try {
    await auth.register({ ...form, device_name: 'web' })
    router.push('/')
  } catch (error) {
    errors.value = fieldErrors(error)
    problem.value = errorMessage(error, t('auth.createFailed'))
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="gate">
    <div class="controls">
      <PaletteToggle />
      <LanguageToggle />
      <ThemeToggle />
    </div>

    <div class="panel">
      <h1>{{ t('auth.createTitle') }}</h1>
      <p class="muted lede">{{ t('auth.createLede') }}</p>

      <form @submit.prevent="submit">
        <FormField v-slot="{ invalid }" :label="t('auth.yourName')" name="name" :errors="errors">
          <input v-model="form.name" class="input" :class="{ 'is-invalid': invalid }" required />
        </FormField>

        <FormField v-slot="{ invalid }" :label="t('common.email')" name="email" :errors="errors">
          <input v-model="form.email" class="input" :class="{ 'is-invalid': invalid }" type="email" required />
        </FormField>

        <FormField
          v-slot="{ invalid }"
          :label="t('common.phone')"
          name="phone"
          :errors="errors"
          :hint="t('common.optional')"
        >
          <input v-model="form.phone" class="input" :class="{ 'is-invalid': invalid }" />
        </FormField>

        <FormField
          v-slot="{ invalid }"
          :label="t('auth.password')"
          name="password"
          :errors="errors"
          :hint="t('auth.passwordHint')"
        >
          <input v-model="form.password" class="input" :class="{ 'is-invalid': invalid }" type="password" required />
        </FormField>

        <FormField :label="t('auth.confirmPassword')" name="password_confirmation" :errors="errors">
          <input v-model="form.password_confirmation" class="input" type="password" required />
        </FormField>

        <p v-if="problem && !Object.keys(errors).length" class="field-error">{{ problem }}</p>

        <button class="btn full" type="submit" :disabled="busy">
          {{ busy ? t('auth.creating') : t('auth.createAccount') }}
        </button>
      </form>

      <p class="muted foot">
        {{ t('auth.haveAccount') }}
        <RouterLink to="/login" class="strong-link">{{ t('auth.signIn') }}</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped>
.gate {
  position: relative;
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: var(--sp-6);
  background:
    radial-gradient(900px 520px at 12% -12%, var(--sidebar-hover) 0%, transparent 62%),
    var(--sidebar-bg);
}
.controls {
  position: absolute;
  top: var(--sp-5);
  right: var(--sp-5);
  display: flex;
  gap: var(--sp-2);
}
.panel {
  width: 100%;
  max-width: 404px;
  padding: var(--sp-8) var(--sp-8) var(--sp-6);
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-3);
  animation: rise 0.4s var(--ease);
}
h1 { font-size: var(--text-xl); }
.lede { margin: 6px 0 var(--sp-5); font-size: var(--text-base); }
form { display: flex; flex-direction: column; gap: var(--sp-3); }
.full { width: 100%; height: 38px; margin-top: var(--sp-1); }
.foot { margin: var(--sp-5) 0 0; font-size: var(--text-sm); text-align: center; }
@keyframes rise { from { opacity: 0; transform: translateY(10px); } }
</style>
