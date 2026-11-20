<script setup>
import AppIcon from './AppIcon.vue'

/*
 * A table action needs to say three things at a glance: what it does, whether
 * it is destructive, and that it is clickable at all. A row of identical grey
 * words says none of them. Icon plus label carries the meaning; the tone
 * carries the consequence; the hover state carries the affordance.
 *
 * The label collapses to a tooltip on narrow screens, but stays in the
 * accessible name at every width.
 */
defineProps({
  icon: { type: String, required: true },
  label: { type: String, required: true },
  // 'default' | 'danger' | 'positive'
  tone: { type: String, default: 'default' },
  disabled: { type: Boolean, default: false },
})
</script>

<template>
  <button
    class="row-action"
    :class="tone"
    type="button"
    :title="label"
    :aria-label="label"
    :disabled="disabled"
  >
    <AppIcon :name="icon" :size="15" />
    <span class="label">{{ label }}</span>
  </button>
</template>

<style scoped>
.row-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 30px;
  padding: 0 10px;
  border: 1px solid transparent;
  border-radius: var(--radius-pill);
  background: transparent;
  color: var(--text-muted);
  font-family: var(--body);
  font-size: var(--text-sm);
  font-weight: 500;
  white-space: nowrap;
  cursor: pointer;
  transition: background 0.14s var(--ease), color 0.14s var(--ease),
    border-color 0.14s var(--ease);
}

.row-action:hover:not(:disabled) {
  background: var(--surface-3);
  border-color: var(--line);
  color: var(--text);
}

/* Destructive actions only turn red on approach — loud enough to catch you,
   quiet enough not to make every row look like a warning. */
.row-action.danger:hover:not(:disabled) {
  background: var(--negative-wash);
  border-color: var(--negative);
  color: var(--negative);
}

.row-action.positive:hover:not(:disabled) {
  background: var(--positive-wash);
  border-color: var(--positive);
  color: var(--positive);
}

.row-action:disabled { opacity: 0.45; cursor: not-allowed; }

@media (max-width: 900px) {
  .label { display: none; }
  .row-action { padding: 0 8px; }
}
</style>
