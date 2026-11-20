<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatMoney } from '@/composables/format'

/**
 * The board: every unit in the portfolio as one tile, coloured by what the
 * landlord actually needs to know — is it let, and is the rent in.
 */
const props = defineProps({
  units: { type: Array, default: () => [] },
  arrearsUnitIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['select'])
const { t } = useI18n()

const groups = computed(() => {
  const byProperty = new Map()
  for (const unit of props.units) {
    const key = unit.property?.id ?? unit.property_id
    if (!byProperty.has(key)) {
      byProperty.set(key, { id: key, name: unit.property?.name ?? '', units: [] })
    }
    byProperty.get(key).units.push(unit)
  }
  return [...byProperty.values()]
})

function state(unit) {
  if (props.arrearsUnitIds.includes(unit.id)) return 'arrears'
  if (unit.status === 'occupied') return 'let'
  if (unit.status === 'maintenance') return 'works'
  return 'vacant'
}

const LEGEND = ['let', 'arrears', 'vacant', 'works']

function tooltip(unit) {
  const tenant = unit.active_lease?.tenant?.full_name
  const rent = formatMoney(unit.rent_amount)
  return tenant ? `${tenant} · ${rent}` : `${t('board.vacantLabel')} · ${rent}`
}
</script>

<template>
  <div class="board">
    <div v-for="group in groups" :key="group.id" class="group">
      <p class="eyebrow">{{ group.name }}</p>
      <div class="tiles">
        <button
          v-for="(unit, i) in group.units"
          :key="unit.id"
          type="button"
          class="tile"
          :class="state(unit)"
          :style="{ animationDelay: `${Math.min(i * 22, 400)}ms` }"
          :title="tooltip(unit)"
          @click="emit('select', unit)"
        >
          <span class="dot" />
          <span class="label">{{ unit.label }}</span>
          <span class="num rent">{{ formatMoney(unit.rent_amount) }}</span>
        </button>
      </div>
    </div>

    <div class="legend">
      <span v-for="key in LEGEND" :key="key" class="legend-item">
        <i :class="key" />{{ t(`board.${key}`) }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.board { display: flex; flex-direction: column; gap: var(--sp-5); }
.group { display: flex; flex-direction: column; gap: var(--sp-2); }
.tiles {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(124px, 1fr));
  gap: var(--sp-2);
}

@media (max-width: 480px) {
  .tiles { grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); }
  .tile { padding: 11px; }
}
.tile {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 3px;
  padding: 14px;
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  background: var(--surface);
  cursor: pointer;
  text-align: left;
  font-family: var(--body);
  animation: tile-in 0.34s var(--ease) backwards;
  transition: transform 0.16s var(--ease), box-shadow 0.16s var(--ease),
    border-color 0.16s var(--ease);
}
.tile:hover { transform: translateY(-2px); box-shadow: var(--shadow-2); }
.dot {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 6px;
  height: 6px;
  border-radius: 50%;
}
.label { font-weight: 600; font-size: var(--text-base); }
.rent { font-size: var(--text-xs); color: var(--text-muted); }

.tile.let .dot { background: var(--positive); }
.tile.arrears { border-color: var(--negative); background: var(--negative-wash); }
.tile.arrears .dot { background: var(--negative); }
.tile.vacant { background: var(--surface-2); border-style: dashed; }
.tile.vacant .dot { background: var(--line-strong); }
.tile.vacant .label { color: var(--text-muted); }
.tile.works { background: var(--warning-wash); border-color: var(--warning); }
.tile.works .dot { background: var(--warning); }

.legend {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-4);
  padding-top: var(--sp-1);
  font-size: var(--text-xs);
  color: var(--text-muted);
}
.legend-item { display: inline-flex; align-items: center; gap: 6px; }
.legend i { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.legend i.let { background: var(--positive); }
.legend i.arrears { background: var(--negative); }
.legend i.vacant { background: var(--line-strong); }
.legend i.works { background: var(--warning); }

@keyframes tile-in { from { opacity: 0; transform: translateY(6px); } }
</style>
