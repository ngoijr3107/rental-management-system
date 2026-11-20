<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { alerts as alertsApi } from '@/api/resources'
import AppIcon from './AppIcon.vue'

const router = useRouter()
const { t } = useI18n()

const open = ref(false)
const items = ref([])
const unread = ref(0)
let timer

async function load() {
  try {
    const { data } = await alertsApi.list()
    items.value = data.data ?? []
    unread.value = data.unread ?? 0
  } catch {
    // A failing bell must never break the page it sits on.
  }
}

async function openItem(alert) {
  open.value = false
  if (!alert.read) {
    alertsApi.read(alert.id).then(load)
  }
  if (alert.link) router.push(alert.link)
}

async function clearAll() {
  await alertsApi.readAll()
  await load()
}

function when(value) {
  return new Date(value).toLocaleString(undefined, {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
  })
}

onMounted(() => {
  load()
  // Cheap polling. A real-time channel would be better, but this needs no
  // extra infrastructure and a landlord checks the bell, not the reverse.
  timer = setInterval(load, 60000)
})
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <div class="bell-wrap">
    <button
      class="bell"
      type="button"
      :title="t('alerts.title')"
      :aria-label="t('alerts.title')"
      @click="open = !open"
    >
      <AppIcon name="activity" :size="17" />
      <span v-if="unread" class="dot num">{{ unread > 9 ? '9+' : unread }}</span>
    </button>

    <div v-if="open" class="sheet-scrim" @click="open = false" />

    <div v-if="open" class="drop card">
      <header>
        <h4>{{ t('alerts.title') }}</h4>
        <button v-if="unread" class="link-action" type="button" @click="clearAll">
          {{ t('alerts.markAllRead') }}
        </button>
      </header>

      <ul v-if="items.length" class="list">
        <li v-for="alert in items" :key="alert.id">
          <button type="button" class="item" :class="{ unread: !alert.read }" @click="openItem(alert)">
            <span class="pip" :class="alert.severity" />
            <span class="text">
              <span class="title">{{ alert.title }}</span>
              <span class="body muted">{{ alert.body }}</span>
              <span class="when muted num">{{ when(alert.created_at) }}</span>
            </span>
          </button>
        </li>
      </ul>

      <p v-else class="empty muted">{{ t('alerts.empty') }}</p>
    </div>
  </div>
</template>

<style scoped>
.bell-wrap { position: relative; }

.bell {
  position: relative;
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  background: var(--surface);
  color: var(--text-muted);
  cursor: pointer;
  transition: color 0.16s var(--ease), border-color 0.16s var(--ease);
}
.bell:hover { color: var(--accent-ink); border-color: var(--accent); }

.dot {
  position: absolute;
  top: -5px;
  right: -5px;
  min-width: 17px;
  height: 17px;
  padding: 0 4px;
  display: grid;
  place-items: center;
  border-radius: var(--radius-pill);
  background: var(--negative);
  color: var(--on-negative);
  font-size: 9px;
  font-weight: 600;
}

.sheet-scrim { position: fixed; inset: 0; z-index: 45; }

.drop {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  z-index: 46;
  width: min(340px, calc(100vw - 32px));
  max-height: 70vh;
  overflow-y: auto;
  box-shadow: var(--shadow-3);
}

header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-2);
  padding: var(--sp-3) var(--sp-4);
  border-bottom: 1px solid var(--line);
}
header h4 { font-size: var(--text-md); }
.link-action {
  border: 0;
  background: none;
  padding: 0;
  color: var(--accent-ink);
  font-family: var(--body);
  font-size: var(--text-sm);
  font-weight: 500;
  cursor: pointer;
}
.link-action:hover { text-decoration: underline; }

.list { list-style: none; margin: 0; padding: 0; }
.list li + li { border-top: 1px solid var(--line); }

.item {
  display: flex;
  gap: 10px;
  width: 100%;
  padding: var(--sp-3) var(--sp-4);
  border: 0;
  background: transparent;
  text-align: left;
  cursor: pointer;
  font-family: var(--body);
}
.item:hover { background: var(--surface-2); }
.item.unread .title { font-weight: 600; }

.pip { width: 7px; height: 7px; margin-top: 6px; border-radius: 50%; flex: none; background: var(--neutral); }
.pip.success { background: var(--positive); }
.pip.warning { background: var(--warning); }
.pip.danger { background: var(--negative); }

.text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.title { font-size: var(--text-base); color: var(--text); }
.body { font-size: var(--text-sm); }
.when { font-size: var(--text-xs); }

.empty { padding: var(--sp-6); text-align: center; font-size: var(--text-base); margin: 0; }
</style>
