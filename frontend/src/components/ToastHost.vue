<script setup>
import { useI18n } from 'vue-i18n'
import { useToastStore } from '@/stores/toast'
import AppIcon from './AppIcon.vue'
const toast = useToastStore()
const { t } = useI18n()
</script>

<template>
  <div class="host" aria-live="polite">
    <TransitionGroup name="toast">
      <div v-for="item in toast.items" :key="item.id" class="toast" :class="item.tone">
        <span>{{ item.message }}</span>
        <button type="button" :aria-label="t('common.close')" @click="toast.dismiss(item.id)"><AppIcon name="close" :size="15" /></button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.host {
  position: fixed;
  right: 18px;
  bottom: 18px;
  z-index: 80;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.toast {
  display: flex;
  align-items: center;
  gap: 14px;
  max-width: 380px;
  padding: 11px 14px;
  border-radius: var(--radius);
  border: 1px solid var(--sidebar-line);
  background: var(--sidebar-bg);
  color: var(--sidebar-text-strong);
  font-size: 13px;
  box-shadow: var(--shadow-3);
  border-left: 3px solid var(--positive);
}
.toast.error { border-left-color: var(--negative); }
.toast button {
  border: 0;
  background: none;
  color: var(--sidebar-text);
  cursor: pointer;
  font-size: 12px;
}
.toast button:hover { color: var(--sidebar-text-strong); }
.toast-enter-active, .toast-leave-active { transition: all 0.2s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(20px); }
</style>
