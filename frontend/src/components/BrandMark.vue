<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  // 'sidebar' sits on the dark panel; 'plain' sits on a card or paper.
  variant: { type: String, default: 'plain' },
  size: { type: Number, default: 28 },
  showName: { type: Boolean, default: true },
})

const auth = useAuthStore()
const { t } = useI18n()

const name = computed(() => auth.brandName || t('app.name'))
const logo = computed(() => auth.logoUrl)
const initials = computed(() => auth.brandInitials)
</script>

<template>
  <div class="brandmark" :class="variant">
    <img
      v-if="logo"
      :src="logo"
      :alt="name"
      class="logo"
      :style="{ height: `${size}px`, width: `${size}px` }"
    />
    <span
      v-else
      class="mark"
      :style="{ height: `${size}px`, width: `${size}px`, fontSize: `${Math.round(size * 0.38)}px` }"
    >
      {{ initials }}
    </span>
    <span v-if="showName" class="wordmark">{{ name }}</span>
  </div>
</template>

<style scoped>
.brandmark { display: flex; align-items: center; gap: 10px; min-width: 0; }

.logo {
  border-radius: 7px;
  object-fit: contain;
  /* A logo on a dark sidebar often has a light background of its own. */
  background: var(--surface);
  padding: 2px;
  flex: none;
}

.mark {
  display: grid;
  place-items: center;
  border-radius: 8px;
  background: var(--accent);
  color: var(--accent-contrast);
  font-family: var(--mono);
  font-weight: 600;
  letter-spacing: 0.02em;
  flex: none;
}

.wordmark {
  font-family: var(--display);
  font-size: var(--text-md);
  font-weight: 600;
  letter-spacing: -0.01em;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.brandmark.sidebar .wordmark { color: var(--sidebar-text-strong); }
</style>
