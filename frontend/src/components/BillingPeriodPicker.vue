<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

/*
 * Payment terms are negotiated, so the number field is always present and
 * always editable. The chips are shortcuts that fill it in, not a mode that
 * replaces it.
 *
 * What is typed is kept separate from what is committed. Clearing the field
 * leaves it cleared while you type the next number; the value is only
 * normalised when you leave the field. An input that rewrites itself
 * mid-keystroke is unusable.
 */
const props = defineProps({
  modelValue: { type: [Number, String], default: 1 },
  disabled: { type: Boolean, default: false },
  min: { type: Number, default: 1 },
  max: { type: Number, default: 24 },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const PRESETS = [1, 3, 6, 12]

/** What is in the box right now — a string, so it can legitimately be empty. */
const raw = ref(String(props.modelValue ?? 1))

watch(
  () => props.modelValue,
  (value) => {
    // Only overwrite the box when the change came from outside it.
    if (String(value) !== raw.value) raw.value = String(value ?? 1)
  },
)

function clamp(value) {
  return Math.min(props.max, Math.max(props.min, Math.round(value)))
}

/** Typing: accept anything, commit only what is already valid. */
function onInput(event) {
  raw.value = event.target.value

  const parsed = Number(raw.value)
  if (raw.value !== '' && Number.isFinite(parsed) && parsed >= props.min && parsed <= props.max) {
    emit('update:modelValue', Math.round(parsed))
  }
}

/** Leaving the field: settle on something sane. */
function onBlur() {
  const parsed = Number(raw.value)
  const settled = raw.value === '' || !Number.isFinite(parsed) ? props.min : clamp(parsed)

  raw.value = String(settled)
  emit('update:modelValue', settled)
}

function choose(months) {
  raw.value = String(months)
  emit('update:modelValue', months)
}
</script>

<template>
  <div class="picker">
    <div class="row-line">
      <input
        class="input num months"
        type="number"
        inputmode="numeric"
        :min="min"
        :max="max"
        :value="raw"
        :disabled="disabled"
        :aria-label="t('leases.termMonths')"
        @input="onInput"
        @blur="onBlur"
      />
      <span class="unit">{{ t('cycle.months', Number(raw) || 1) }}</span>
    </div>

    <div class="presets" role="group" :aria-label="t('leases.commonTerms')">
      <button
        v-for="preset in PRESETS"
        :key="preset"
        type="button"
        class="preset"
        :class="{ on: Number(raw) === preset }"
        :disabled="disabled"
        @click="choose(preset)"
      >
        {{ t('cycle.everyMonths', preset) }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.picker { display: flex; flex-direction: column; gap: var(--sp-2); }

.row-line { display: flex; align-items: center; gap: var(--sp-2); }
.months { width: 96px; flex: none; }
.unit { font-size: var(--text-base); color: var(--text-muted); }

.presets { display: flex; flex-wrap: wrap; gap: 6px; }

.preset {
  height: 28px;
  padding: 0 11px;
  border: 1px solid var(--line);
  border-radius: var(--radius-pill);
  background: var(--surface-2);
  color: var(--text-muted);
  font-family: var(--body);
  font-size: var(--text-sm);
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s var(--ease), color 0.15s var(--ease),
    border-color 0.15s var(--ease);
}

.preset:hover:not(:disabled) { border-color: var(--accent); color: var(--accent-ink); }

.preset.on {
  background: var(--accent);
  border-color: transparent;
  color: var(--accent-contrast);
}

.preset:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
