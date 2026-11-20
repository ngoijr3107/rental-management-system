<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from './AppIcon.vue'

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  width: { type: String, default: '520px' },
})

const emit = defineEmits(['close'])
const { t } = useI18n()

function onKey(event) {
  if (event.key === 'Escape') emit('close')
}

onMounted(() => {
  document.addEventListener('keydown', onKey)
  document.body.style.overflow = 'hidden'
})

onUnmounted(() => {
  document.removeEventListener('keydown', onKey)
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="scrim" @click.self="emit('close')">
    <div class="sheet" :style="{ maxWidth: width }" role="dialog" aria-modal="true">
      <header>
        <div>
          <h3>{{ title }}</h3>
          <p v-if="subtitle" class="muted sub">{{ subtitle }}</p>
        </div>
        <button class="btn btn-quiet" type="button" :aria-label="t('common.close')" @click="emit('close')"><AppIcon name="close" :size="15" /></button>
      </header>
      <div class="body">
        <slot />
      </div>
      <footer v-if="$slots.actions">
        <slot name="actions" />
      </footer>
    </div>
  </div>
</template>

<style scoped>
.scrim {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 6vh 16px 24px;
  background: var(--scrim);
  backdrop-filter: blur(2px);
  overflow-y: auto;
  animation: fade 0.15s var(--ease);
}
.sheet {
  width: 100%;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-3);
  animation: rise 0.18s var(--ease);
}
header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 18px;
  border-bottom: 1px solid var(--line);
}
header h3 { font-size: 16px; }
.sub { margin: 3px 0 0; font-size: 12px; }
.body { padding: var(--sp-5); }

@media (max-width: 620px) {
  .scrim { padding: 0; align-items: flex-end; }
  .sheet {
    max-width: none !important;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    max-height: 92vh;
    display: flex;
    flex-direction: column;
  }
  .body { overflow-y: auto; }
  footer { border-radius: 0; }
  footer .btn { flex: 1; }
}
footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 14px 18px;
  border-top: 1px solid var(--line);
  background: var(--surface-2);
  border-radius: 0 0 var(--radius-lg) var(--radius-lg);
}
@keyframes fade { from { opacity: 0; } }
@keyframes rise { from { opacity: 0; transform: translateY(-8px); } }
</style>
