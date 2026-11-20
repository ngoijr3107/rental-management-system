<script setup>
import { useI18n } from 'vue-i18n'
import { PALETTES, usePrefsStore } from '@/stores/prefs'

const prefs = usePrefsStore()
const { t } = useI18n()
</script>

<template>
  <div class="swatches" role="group" :aria-label="t('prefs.palette')">
    <button
      v-for="option in PALETTES"
      :key="option.id"
      type="button"
      class="swatch"
      :class="{ on: prefs.palette === option.id }"
      :style="{ '--chip': option.swatch }"
      :title="t(option.labelKey)"
      :aria-label="t(option.labelKey)"
      :aria-pressed="prefs.palette === option.id"
      @click="prefs.setPalette(option.id)"
    />
  </div>
</template>

<style scoped>
.swatches {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0 7px;
  height: 32px;
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  background: var(--surface);
}
.swatch {
  width: 13px;
  height: 13px;
  padding: 0;
  border-radius: 50%;
  border: 1px solid rgba(0, 0, 0, 0.16);
  background: var(--chip);
  cursor: pointer;
  transition: transform 0.16s var(--ease), box-shadow 0.16s var(--ease);
}
.swatch:hover { transform: scale(1.16); }
.swatch.on {
  box-shadow: 0 0 0 2px var(--surface), 0 0 0 3.5px var(--chip);
}
</style>
