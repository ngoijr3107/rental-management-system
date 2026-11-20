<script setup>
defineProps({
  label: { type: String, required: true },
  errors: { type: Object, default: () => ({}) },
  name: { type: String, required: true },
  hint: { type: String, default: '' },
})
</script>

<template>
  <div class="field">
    <label :for="name">{{ label }}</label>
    <slot :invalid="Boolean(errors[name])" />
    <p v-if="errors[name]" class="field-error">{{ errors[name][0] }}</p>
    <p v-else-if="hint" class="muted hint">{{ hint }}</p>
  </div>
</template>

<style scoped>
.hint { margin: 0; font-size: 11px; }
</style>
