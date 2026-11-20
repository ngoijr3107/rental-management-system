<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  status: { type: String, default: '' },
  tone: { type: String, default: '' },
})

const { t, te } = useI18n()

const TONES = {
  paid: 'positive',
  active: 'positive',
  resolved: 'positive',
  occupied: 'accent',
  in_progress: 'accent',
  partially_paid: 'warning',
  maintenance: 'warning',
  open: 'warning',
  medium: 'warning',
  unpaid: 'negative',
  overdue: 'negative',
  urgent: 'negative',
  high: 'negative',
  vacant: 'neutral',
  low: 'neutral',
  terminated: 'faded',
  expired: 'faded',
  cancelled: 'faded',
}

const resolved = computed(() => props.tone || TONES[props.status] || 'neutral')

const label = computed(() =>
  te(`status.${props.status}`) ? t(`status.${props.status}`) : props.status,
)
</script>

<template>
  <span class="pill" :class="`pill-${resolved}`"><i class="dot" />{{ label }}</span>
</template>

<style scoped>
.pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 24px;
  padding: 0 10px 0 8px;
  border-radius: var(--radius-pill);
  font-family: var(--body);
  font-size: var(--text-sm);
  font-weight: 500;
  white-space: nowrap;
  border: 1px solid transparent;
}

/* The dot carries the colour, so the label stays quiet and legible. */
.dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  flex: none;
}
.pill-positive { background: var(--positive-wash); color: var(--positive); }
.pill-warning { background: var(--warning-wash); color: var(--warning); }
.pill-negative { background: var(--negative-wash); color: var(--negative); }
.pill-accent { background: var(--accent-wash); color: var(--accent-ink); }
.pill-neutral { background: var(--neutral-wash); color: var(--neutral); }
.pill-slate { background: var(--neutral-wash); color: var(--neutral); }
.pill-faded { background: var(--surface-3); color: var(--text-muted); }
</style>
