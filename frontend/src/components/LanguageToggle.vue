<script setup>
import { useI18n } from 'vue-i18n'
import { LOCALES } from '@/i18n'
import { usePrefsStore } from '@/stores/prefs'

const prefs = usePrefsStore()
const { locale, t } = useI18n()

function choose(code) {
  locale.value = code
  prefs.setLocale(code)
}
</script>

<template>
  <div class="switch" role="group" :aria-label="t('prefs.language')">
    <button
      v-for="option in LOCALES"
      :key="option.code"
      type="button"
      class="opt"
      :class="{ on: locale === option.code }"
      :title="t(option.labelKey)"
      :aria-pressed="locale === option.code"
      @click="choose(option.code)"
    >
      {{ option.short }}
    </button>
  </div>
</template>

<style scoped>
.switch {
  display: inline-flex;
  padding: 2px;
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  background: var(--surface);
}
.opt {
  border: 0;
  border-radius: 5px;
  background: transparent;
  padding: 0 8px;
  height: 26px;
  font-family: var(--mono);
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 0.06em;
  color: var(--text-faint);
  cursor: pointer;
  transition: background 0.16s var(--ease), color 0.16s var(--ease);
}
.opt:hover { color: var(--text); }
.opt.on { background: var(--accent); color: var(--accent-contrast); }
</style>
