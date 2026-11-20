<script setup>
import { useI18n } from 'vue-i18n'
import { downloadCsv, printReport } from '@/composables/useCsv'
import SkeletonTable from '@/components/SkeletonTable.vue'
import BrandMark from '@/components/BrandMark.vue'
import AppIcon from '@/components/AppIcon.vue'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  title: { type: String, required: true },
  lede: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  generatedAt: { type: String, default: '' },
  // CSV wiring
  filename: { type: String, default: 'report' },
  columns: { type: Array, default: () => [] },
  rows: { type: Array, default: () => [] },
  extra: { type: Array, default: () => [] },
})

const { t } = useI18n()
const auth = useAuthStore()

function exportCsv() {
  downloadCsv(props.filename, props.columns, props.rows, props.extra)
}
</script>

<template>
  <section class="report">
    <!-- Only reaches paper: gives a printed report a letterhead. -->
    <header class="letterhead print-only">
      <BrandMark :size="34" />
      <div class="issuer">
        <p v-if="auth.settings?.business_phone">{{ auth.settings.business_phone }}</p>
        <p v-if="auth.settings?.business_email">{{ auth.settings.business_email }}</p>
        <p v-if="auth.settings?.business_address">{{ auth.settings.business_address }}</p>
      </div>
    </header>

    <header class="head">
      <div>
        <h3>{{ title }}</h3>
        <p v-if="lede" class="muted lede">{{ lede }}</p>
      </div>
      <div class="row no-print">
        <button
          class="btn btn-ghost btn-sm"
          type="button"
          :disabled="!rows.length"
          @click="exportCsv"
        >
          <AppIcon name="download" :size="14" />
          {{ t('reports.exportCsv') }}
        </button>
        <button class="btn btn-ghost btn-sm" type="button" @click="printReport">
          <AppIcon name="printer" :size="14" />
          {{ t('reports.print') }}
        </button>
      </div>
    </header>

    <div v-if="$slots.filters" class="filters no-print">
      <slot name="filters" />
    </div>

    <div v-if="loading" class="pad"><SkeletonTable :rows="6" :columns="6" /></div>
    <slot v-else />

    <p v-if="generatedAt && !loading" class="muted stamp">
      {{ t('reports.generatedAt', { at: generatedAt }) }}
    </p>
  </section>
</template>

<style scoped>
.report {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  box-shadow: var(--shadow-2);
  overflow: hidden;
}
.head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--sp-3);
  padding: var(--sp-5) var(--sp-6);
  border-bottom: 1px solid var(--line);
}
.head h3 { font-size: var(--text-lg); }
.lede { margin: 3px 0 0; font-size: var(--text-sm); max-width: 62ch; }
.pad { padding: var(--sp-2) 0; }

.letterhead { display: none; }
.issuer p { margin: 0; font-size: 10pt; text-align: right; }

@media print {
  .letterhead {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--sp-4);
    padding: var(--sp-4) var(--sp-5);
    border-bottom: 2px solid #333;
  }
}
.stamp {
  margin: 0;
  padding: var(--sp-4) var(--sp-6);
  border-top: 1px solid var(--line);
  font-size: var(--text-xs);
}

@media (max-width: 620px) {
  .head { flex-direction: column; align-items: stretch; }
  .head .row { width: 100%; }
  .head .row .btn { flex: 1; }
  .lede { max-width: none; }
}
</style>
