<script setup>
defineProps({
  // [{ label, value, tone?: 'money' | 'positive' | 'negative' | 'warning' }]
  stats: { type: Array, required: true },
})
</script>

<template>
  <div class="strip">
    <div v-for="stat in stats" :key="stat.label" class="stat">
      <p class="eyebrow">{{ stat.label }}</p>
      <p class="num value" :class="stat.tone">{{ stat.value }}</p>
      <p v-if="stat.note" class="muted note">{{ stat.note }}</p>
    </div>
  </div>
</template>

<style scoped>
.strip {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: var(--sp-6);
  padding: var(--sp-6);
  border-bottom: 1px solid var(--line);
}
.value {
  margin: 5px 0 0;
  font-size: 22px;
  font-weight: 600;
  letter-spacing: -0.025em;
}
.value.money { color: var(--money); }
.value.positive { color: var(--positive); }
.value.negative { color: var(--negative); }
.value.warning { color: var(--warning); }
.note { margin: 2px 0 0; font-size: var(--text-xs); }
</style>
