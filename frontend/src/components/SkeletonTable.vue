<script setup>
defineProps({
  rows: { type: Number, default: 5 },
  columns: { type: Number, default: 5 },
})
</script>

<template>
  <div class="skel" aria-hidden="true">
    <div v-for="row in rows" :key="row" class="skel-row" :style="{ animationDelay: `${row * 45}ms` }">
      <span
        v-for="col in columns"
        :key="col"
        class="skeleton cell"
        :style="{ width: col === 1 ? '32%' : `${10 + ((col * 7) % 14)}%` }"
      />
    </div>
  </div>
</template>

<style scoped>
.skel { padding: 4px 0; }
.skel-row {
  display: flex;
  align-items: center;
  gap: var(--sp-4);
  padding: 13px var(--sp-4);
  border-bottom: 1px solid var(--line);
  animation: fade-up 0.35s var(--ease) backwards;
}
.skel-row:last-child { border-bottom: none; }
.cell { height: 11px; display: block; }
@keyframes fade-up { from { opacity: 0; } }
</style>
