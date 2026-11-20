<script setup>
defineProps({
  title: { type: String, required: true },
  eyebrow: { type: String, default: '' },
  count: { type: [Number, String], default: null },
})
</script>

<template>
  <header class="page-head">
    <div>
      <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
      <h1>
        {{ title }}
        <span v-if="count !== null" class="num count">{{ count }}</span>
      </h1>
    </div>
    <div class="row row-wrap">
      <slot name="actions" />
    </div>
  </header>
</template>

<style scoped>
.page-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 14px;
  flex-wrap: wrap;
}
h1 {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: var(--text-3xl);
  margin-top: 4px;
  letter-spacing: -0.03em;
}
.count {
  padding: 3px 10px;
  border-radius: var(--radius-pill);
  background: var(--surface-3);
  font-family: var(--mono);
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--text-muted);
}

@media (max-width: 620px) {
  .page-head { align-items: stretch; gap: var(--sp-3); }
  .page-head .row { width: 100%; }
  .page-head .row > * { flex: 1 1 auto; }
}
</style>
