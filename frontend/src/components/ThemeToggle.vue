<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePrefsStore } from '@/stores/prefs'
import AppIcon from './AppIcon.vue'

const prefs = usePrefsStore()
const { t } = useI18n()

const MODES = {
  light: { icon: 'sun', key: 'prefs.light' },
  dark: { icon: 'moon', key: 'prefs.dark' },
  system: { icon: 'system', key: 'prefs.system' },
}

const current = computed(() => MODES[prefs.theme] ?? MODES.system)
const title = computed(() => t('prefs.themeNow', { mode: t(current.value.key) }))
</script>

<template>
  <button
    class="toggle"
    type="button"
    :title="title"
    :aria-label="title"
    @click="prefs.cycleTheme()"
  >
    <span class="glyph" :key="prefs.theme">
      <AppIcon :name="current.icon" :size="16" />
    </span>
  </button>
</template>

<style scoped>
.toggle {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  background: var(--surface);
  color: var(--text-muted);
  cursor: pointer;
  transition: color 0.16s var(--ease), border-color 0.16s var(--ease), background 0.16s var(--ease);
}
.toggle:hover { color: var(--accent-ink); border-color: var(--accent); background: var(--accent-wash); }
.glyph {
  display: grid;
  place-items: center;
  animation: pop 0.28s var(--ease);
}
@keyframes pop {
  from { opacity: 0; transform: rotate(-35deg) scale(0.7); }
  to { opacity: 1; transform: none; }
}
</style>
