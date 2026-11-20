<script setup>
defineProps({
  title: { type: String, required: true },
  hint: { type: String, default: '' },
})
</script>

<template>
  <div class="empty">
    <span class="mark" aria-hidden="true" />
    <p class="title">{{ title }}</p>
    <p v-if="hint" class="muted hint">{{ hint }}</p>
    <div v-if="$slots.default" class="action">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 52px var(--sp-6);
  text-align: center;
}
.mark {
  width: 34px;
  height: 34px;
  margin-bottom: 4px;
  border-radius: 12px;
  border: 1px dashed var(--line-strong);
  background: var(--surface-2);
}
.title { margin: 0; font-family: var(--display); font-size: 15px; font-weight: 600; }
.hint { margin: 0; max-width: 44ch; font-size: var(--text-base); }
.action { margin-top: 6px; }
</style>
