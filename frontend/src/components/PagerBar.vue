<script setup>
import { useI18n } from 'vue-i18n'
const { t } = useI18n()

defineProps({
  meta: { type: Object, default: null },
})
const emit = defineEmits(['page'])
</script>

<template>
  <div v-if="meta && meta.last_page > 1" class="pager">
    <span class="muted">
      <span class="num">{{ meta.from ?? 0 }}–{{ meta.to ?? 0 }}</span> {{ t('common.of') }}
      <span class="num">{{ meta.total }}</span>
    </span>
    <div class="row">
      <button
        class="btn btn-ghost btn-sm"
        type="button"
        :disabled="meta.current_page <= 1"
        @click="emit('page', meta.current_page - 1)"
      >
        {{ t('common.previous') }}
      </button>
      <span class="num page-of">{{ meta.current_page }} / {{ meta.last_page }}</span>
      <button
        class="btn btn-ghost btn-sm"
        type="button"
        :disabled="meta.current_page >= meta.last_page"
        @click="emit('page', meta.current_page + 1)"
      >
        {{ t('common.next') }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: var(--sp-4) var(--sp-6);
  border-top: 1px solid var(--line);
  font-size: 12px;
}
.page-of { font-size: 12px; color: var(--text-muted); }
</style>
