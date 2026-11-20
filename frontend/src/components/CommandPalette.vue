<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { usePrefsStore } from '@/stores/prefs'

const router = useRouter()
const prefs = usePrefsStore()
const { t, locale } = useI18n()

const open = ref(false)
const query = ref('')
const cursor = ref(0)
const input = ref(null)

const NAV = [
  { to: '/', key: 'nav.dashboard' },
  { to: '/properties', key: 'nav.properties' },
  { to: '/units', key: 'nav.units' },
  { to: '/tenants', key: 'nav.tenants' },
  { to: '/leases', key: 'nav.leases' },
  { to: '/invoices', key: 'nav.invoices' },
  { to: '/payments', key: 'nav.payments' },
  { to: '/maintenance', key: 'nav.maintenance' },
  { to: '/reports/rent-roll', key: 'reports.rentRoll.tab' },
  { to: '/reports/arrears', key: 'reports.arrears.tab' },
  { to: '/reports/collections', key: 'reports.collections.tab' },
  { to: '/reports/statement', key: 'reports.statement.tab' },
  { to: '/profile', key: 'profile.title' },
]

const commands = computed(() => {
  const nav = NAV.map((item) => ({
    id: `nav:${item.to}`,
    group: t('palette.navigate'),
    label: t(item.key),
    run: () => router.push(item.to),
  }))

  const actions = [
    {
      id: 'theme',
      group: t('palette.actions'),
      label: `${t('prefs.theme')} — ${t('prefs.light')} / ${t('prefs.dark')} / ${t('prefs.system')}`,
      run: () => prefs.cycleTheme(),
    },
    {
      id: 'lang',
      group: t('palette.actions'),
      label: `${t('prefs.language')} — ${t('prefs.english')} / ${t('prefs.swahili')}`,
      run: () => {
        const next = locale.value === 'en' ? 'sw' : 'en'
        locale.value = next
        prefs.setLocale(next)
      },
    },
  ]

  return [...nav, ...actions]
})

const results = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return commands.value
  return commands.value.filter((item) => item.label.toLowerCase().includes(q))
})

const grouped = computed(() => {
  const map = new Map()
  for (const item of results.value) {
    if (!map.has(item.group)) map.set(item.group, [])
    map.get(item.group).push(item)
  }
  return [...map.entries()].map(([group, items]) => ({ group, items }))
})

watch(results, () => {
  cursor.value = 0
})

function show() {
  open.value = true
  query.value = ''
  cursor.value = 0
  nextTick(() => input.value?.focus())
}

function hide() {
  open.value = false
}

function pick(item) {
  hide()
  item.run()
}

function onKey(event) {
  const trigger = (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k'
  if (trigger) {
    event.preventDefault()
    open.value ? hide() : show()
    return
  }

  if (!open.value) return

  if (event.key === 'Escape') {
    event.preventDefault()
    hide()
  } else if (event.key === 'ArrowDown') {
    event.preventDefault()
    cursor.value = Math.min(cursor.value + 1, results.value.length - 1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    cursor.value = Math.max(cursor.value - 1, 0)
  } else if (event.key === 'Enter') {
    event.preventDefault()
    const item = results.value[cursor.value]
    if (item) pick(item)
  }
}

/** Flat index so arrow keys move across group boundaries. */
function indexOf(item) {
  return results.value.indexOf(item)
}

onMounted(() => document.addEventListener('keydown', onKey))
onUnmounted(() => document.removeEventListener('keydown', onKey))

defineExpose({ show })
</script>

<template>
  <div v-if="open" class="scrim" @click.self="hide">
    <div class="palette" role="dialog" aria-modal="true">
      <input
        ref="input"
        v-model="query"
        class="query"
        type="text"
        :placeholder="t('palette.placeholder')"
        :aria-label="t('palette.placeholder')"
      />
      <div class="results">
        <template v-if="results.length">
          <div v-for="section in grouped" :key="section.group" class="group">
            <p class="eyebrow">{{ section.group }}</p>
            <button
              v-for="item in section.items"
              :key="item.id"
              type="button"
              class="item"
              :class="{ on: indexOf(item) === cursor }"
              @click="pick(item)"
              @mouseenter="cursor = indexOf(item)"
            >
              {{ item.label }}
            </button>
          </div>
        </template>
        <p v-else class="muted none">{{ t('palette.noResults') }}</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.scrim {
  position: fixed;
  inset: 0;
  z-index: 90;
  display: flex;
  justify-content: center;
  padding: 12vh 16px 24px;
  background: var(--scrim);
  backdrop-filter: blur(3px);
  animation: fade 0.14s var(--ease);
}
.palette {
  width: 100%;
  max-width: 480px;
  height: fit-content;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-3);
  overflow: hidden;
  animation: rise 0.18s var(--ease);
}
.query {
  width: 100%;
  height: 48px;
  padding: 0 18px;
  border: 0;
  border-bottom: 1px solid var(--line);
  background: transparent;
  color: var(--text);
  font-family: var(--body);
  font-size: var(--text-md);
}
.query:focus { outline: none; }
.results { max-height: 46vh; overflow-y: auto; padding: 8px; }
.group { margin-bottom: 6px; }
.group .eyebrow { padding: 6px 10px 4px; margin: 0; }
.item {
  display: block;
  width: 100%;
  text-align: left;
  padding: 8px 10px;
  border: 0;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--text);
  font-family: var(--body);
  font-size: var(--text-base);
  cursor: pointer;
}
.item.on { background: var(--accent-wash); color: var(--accent-ink); }
.none { padding: 18px; text-align: center; }
@keyframes fade { from { opacity: 0; } }
@keyframes rise { from { opacity: 0; transform: translateY(-10px) scale(0.985); } }
</style>
