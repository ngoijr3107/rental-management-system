<script setup>
import { onErrorCaptured, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const { t } = useI18n()
const router = useRouter()

const failed = ref(false)
const detail = ref('')

/*
 * A thrown error inside a view used to blank the whole app. Catching it here
 * keeps the shell — and therefore the navigation — alive, so the person can
 * move somewhere else instead of reaching for the reload button.
 */
onErrorCaptured((error) => {
  failed.value = true
  detail.value = error?.message ?? String(error)
  console.error('[Rent Manager] view error:', error)
  return false
})

function recover() {
  failed.value = false
  detail.value = ''
  router.push('/')
}
</script>

<template>
  <div v-if="failed" class="crash card">
    <h2>{{ t('errors.crashed') }}</h2>
    <p class="muted">{{ t('errors.crashedHint') }}</p>
    <pre v-if="detail" class="detail num">{{ detail }}</pre>
    <div class="row">
      <button class="btn" type="button" @click="recover">{{ t('errors.backToDashboard') }}</button>
      <button class="btn btn-ghost" type="button" @click="() => window.location.reload()">
        {{ t('errors.reload') }}
      </button>
    </div>
  </div>
  <slot v-else />
</template>

<style scoped>
.crash { padding: var(--sp-8); max-width: 620px; }
h2 { font-size: var(--text-xl); margin-bottom: var(--sp-2); }
.detail {
  margin: var(--sp-4) 0;
  padding: var(--sp-3) var(--sp-4);
  border-radius: var(--radius-sm);
  background: var(--surface-2);
  color: var(--text-muted);
  font-size: var(--text-sm);
  white-space: pre-wrap;
  overflow-x: auto;
}
</style>
