<script setup>
import { useI18n } from 'vue-i18n'
import ModalDialog from './ModalDialog.vue'

defineProps({
  title: { type: String, required: true },
  message: { type: String, required: true },
  confirmLabel: { type: String, default: '' },
  danger: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'close'])
const { t } = useI18n()
</script>

<template>
  <ModalDialog :title="title" width="420px" @close="emit('close')">
    <p class="message">{{ message }}</p>
    <slot />
    <template #actions>
      <button class="btn btn-ghost" type="button" @click="emit('close')">{{ t('common.cancel') }}</button>
      <button
        class="btn"
        :class="{ 'btn-danger': danger }"
        type="button"
        :disabled="busy"
        @click="emit('confirm')"
      >
        {{ busy ? t('common.working') : confirmLabel || t('common.yes') }}
      </button>
    </template>
  </ModalDialog>
</template>

<style scoped>
.message { margin: 0; color: var(--text-muted); }
</style>
