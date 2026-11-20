<script setup>
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import AppShell from '@/components/AppShell.vue'
import ToastHost from '@/components/ToastHost.vue'
import ErrorBoundary from '@/components/ErrorBoundary.vue'

const route = useRoute()
const auth = useAuthStore()
const { t } = useI18n()

const bare = computed(() => Boolean(route.meta.guest))

// The browser tab carries the account's name once settings have loaded.
watch(
  () => auth.brandName,
  (name) => {
    document.title = name || t('app.name')
  },
  { immediate: true },
)
</script>

<template>
  <a class="skip" href="#main">{{ t('errors.skipToContent') }}</a>

  <RouterView v-if="bare" />
  <AppShell v-else>
    <ErrorBoundary>
      <RouterView v-slot="{ Component, route: current }">
        <Transition name="page" mode="out-in">
          <component :is="Component" :key="current.path" />
        </Transition>
      </RouterView>
    </ErrorBoundary>
  </AppShell>
  <ToastHost />
</template>

<style scoped>
/* Keyboard users get past the navigation without tabbing through 12 links. */
.skip {
  position: absolute;
  left: -9999px;
  top: 0;
  z-index: 100;
  padding: 10px 16px;
  border-radius: 0 0 var(--radius-sm) 0;
  background: var(--accent);
  color: var(--accent-contrast);
  font-size: var(--text-base);
  font-weight: 500;
}
.skip:focus { left: 0; }
</style>
