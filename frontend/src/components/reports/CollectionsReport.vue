<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { reports } from '@/api/resources'
import { errorMessage } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import { formatMoney } from '@/composables/format'
import ReportShell from './ReportShell.vue'
import StatStrip from './StatStrip.vue'

const { t } = useI18n()
const toast = useToastStore()
const data = ref(null)
const loading = ref(true)
const months = ref(12)

const columns = [
  { key: 'label', label: 'Month' },
  { key: 'invoices', label: 'Invoices' },
  { key: 'billed', label: 'Billed' },
  { key: 'collected', label: 'Collected' },
  { key: 'variance', label: 'Variance' },
  { key: 'rate', label: 'Collection rate %' },
]

const stats = computed(() => {
  const totals = data.value?.totals
  if (!totals) return []
  return [
    { label: t('reports.collections.billed'), value: formatMoney(totals.billed) },
    { label: t('reports.collections.collected'), value: formatMoney(totals.collected), tone: 'money' },
    { label: t('reports.collections.uncollected'), value: formatMoney(totals.uncollected), tone: 'negative' },
    {
      label: t('reports.collections.rate'),
      value: totals.rate === null ? '—' : `${totals.rate}%`,
      tone: totals.rate >= 90 ? 'positive' : 'warning',
      note: totals.recent_rate !== null ? t('reports.collections.recent', { rate: totals.recent_rate }) : '',
    },
  ]
})

/** Chart geometry — plain SVG, no charting dependency. */
const chart = computed(() => {
  const rows = data.value?.rows ?? []
  if (!rows.length) return null

  const width = 720
  const height = 190
  const padding = { top: 12, bottom: 26, left: 4, right: 4 }
  const peak = Math.max(...rows.flatMap((r) => [r.billed, r.collected]), 1)
  const slot = (width - padding.left - padding.right) / rows.length
  const barW = Math.min(slot * 0.32, 20)
  const plot = height - padding.top - padding.bottom

  return {
    width,
    height,
    bars: rows.map((row, i) => {
      const x = padding.left + slot * i + slot / 2
      const billedH = (row.billed / peak) * plot
      const collectedH = (row.collected / peak) * plot
      return {
        label: row.label,
        short: row.label.split(' ')[0],
        billed: { x: x - barW - 1, y: padding.top + plot - billedH, h: billedH, w: barW },
        collected: { x: x + 1, y: padding.top + plot - collectedH, h: collectedH, w: barW },
        tx: x,
        ty: height - 8,
        title: `${row.label}: ${formatMoney(row.billed)} / ${formatMoney(row.collected)}`,
      }
    }),
    baseline: padding.top + plot,
  }
})

async function load() {
  loading.value = true
  try {
    const response = await reports.collections({ months: months.value })
    data.value = response.data
  } catch (error) {
    toast.error(errorMessage(error, t('reports.loadFailed')))
  } finally {
    loading.value = false
  }
}

watch(months, load)
onMounted(load)
</script>

<template>
  <ReportShell
    :title="t('reports.collections.title')"
    :lede="t('reports.collections.lede')"
    :loading="loading"
    :generated-at="data?.generated_at"
    filename="collections"
    :columns="columns"
    :rows="data?.rows ?? []"
  >
    <template #filters>
      <div class="field">
        <label for="months">{{ t('reports.collections.window') }}</label>
        <select id="months" v-model.number="months" class="select">
          <option :value="6">6</option>
          <option :value="12">12</option>
          <option :value="24">24</option>
        </select>
      </div>
    </template>

    <template v-if="data">
      <StatStrip :stats="stats" />

      <figure v-if="chart" class="chart">
        <figcaption class="legend">
          <span><i class="billed" />{{ t('reports.collections.billed') }}</span>
          <span><i class="collected" />{{ t('reports.collections.collected') }}</span>
        </figcaption>
        <svg :viewBox="`0 0 ${chart.width} ${chart.height}`" role="img">
          <line
            :x1="0" :x2="chart.width" :y1="chart.baseline" :y2="chart.baseline"
            stroke="var(--line-strong)" stroke-width="1"
          />
          <g v-for="bar in chart.bars" :key="bar.label">
            <title>{{ bar.title }}</title>
            <rect
              :x="bar.billed.x" :y="bar.billed.y" :width="bar.billed.w" :height="bar.billed.h"
              rx="2" fill="var(--line-strong)"
            />
            <rect
              :x="bar.collected.x" :y="bar.collected.y" :width="bar.collected.w" :height="bar.collected.h"
              rx="2" fill="var(--money)"
            />
            <text :x="bar.tx" :y="bar.ty" text-anchor="middle" class="tick">{{ bar.short }}</text>
          </g>
        </svg>
      </figure>

      <div class="table-scroll">

        <table class="table">
        <thead>
          <tr>
            <th>{{ t('reports.collections.month') }}</th>
            <th class="right">{{ t('invoices.title') }}</th>
            <th class="right">{{ t('reports.collections.billed') }}</th>
            <th class="right">{{ t('reports.collections.collected') }}</th>
            <th class="right">{{ t('reports.collections.rate') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in data.rows" :key="row.month">
            <td>{{ row.label }}</td>
            <td class="right num muted">{{ row.invoices }}</td>
            <td class="right num">{{ formatMoney(row.billed) }}</td>
            <td class="right num paid">{{ formatMoney(row.collected) }}</td>
            <td class="right num" :class="row.rate === null ? 'muted' : row.rate >= 90 ? 'good' : 'bad'">
              {{ row.rate === null ? '—' : `${row.rate}%` }}
            </td>
          </tr>
        </tbody>
        </table>
      </div>
    </template>
  </ReportShell>
</template>

<style scoped>
.chart { margin: 0; padding: var(--sp-5); border-bottom: 1px solid var(--line); }
.chart svg { width: 100%; height: auto; display: block; }
.legend { display: flex; gap: var(--sp-4); font-size: var(--text-xs); color: var(--text-muted); margin-bottom: var(--sp-3); }
.legend span { display: inline-flex; align-items: center; gap: 6px; }
.legend i { width: 9px; height: 9px; border-radius: 2px; display: inline-block; }
.legend i.billed { background: var(--line-strong); }
.legend i.collected { background: var(--money); }
.tick { font-family: var(--mono); font-size: 9px; fill: var(--text-faint); }
.paid { color: var(--positive); font-weight: 500; }
.good { color: var(--positive); }
.bad { color: var(--negative); }
</style>
