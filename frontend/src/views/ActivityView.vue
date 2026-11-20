<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { activity, team } from '@/api/resources'
import { errorMessage } from '@/api/client'
import { useCollection } from '@/composables/useCollection'
import { useAuthStore } from '@/stores/auth'
import PageHeader from '@/components/PageHeader.vue'
import PagerBar from '@/components/PagerBar.vue'
import EmptyState from '@/components/EmptyState.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'

const { t, te } = useI18n()
const auth = useAuthStore()
const { items, meta, loading, filters, load, goToPage } = useCollection(activity.list, {
  action: '',
  user_id: '',
  from: '',
  to: '',
})

const ACTIONS = [
  'payment.recorded',
  'payment.reversed',
  'invoice.generated',
  'invoice.cancelled',
  'lease.started',
  'lease.terminated',
  'property.deleted',
  'unit.deleted',
  'tenant.deleted',
  'user.added',
  'user.updated',
  'user.removed',
  'settings.updated',
  'settings.logo',
  'profile.updated',
  'profile.password',
  'unit.photos',
]

const people = ref([])

/** Money and access changes are the ones worth spotting in a long list. */
function tone(action) {
  if (action.startsWith('payment.reversed') || action.endsWith('.deleted') || action === 'user.removed') {
    return 'negative'
  }
  if (action.startsWith('payment.') || action.startsWith('invoice.')) return 'money'
  if (action.startsWith('user.') || action.startsWith('settings.')) return 'accent'
  return 'neutral'
}

function label(action) {
  // Keys use underscores because vue-i18n reads a dot as a path separator.
  const key = `activity.actions.${action.replace(/\./g, '_')}`
  return te(key) ? t(key) : action
}

function when(value) {
  return new Date(value).toLocaleString(undefined, {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
  })
}

onMounted(async () => {
  if (auth.can('users.manage')) {
    try {
      const { data } = await team.list()
      people.value = data.data ?? []
    } catch {
      people.value = []
    }
  }
  await load()
})
</script>

<template>
  <div class="stack">
    <PageHeader
      :title="t('activity.title')"
      :eyebrow="t('nav.admin')"
      :count="meta?.total ?? null"
    />

    <p class="card notice">{{ t('activity.lede') }}</p>

    <section class="card">
      <div class="filters">
        <div class="field">
          <label for="action">{{ t('activity.action') }}</label>
          <select id="action" v-model="filters.action" class="select">
            <option value="">{{ t('activity.anyAction') }}</option>
            <option v-for="action in ACTIONS" :key="action" :value="action">{{ label(action) }}</option>
          </select>
        </div>
        <div v-if="people.length" class="field">
          <label for="who">{{ t('activity.who') }}</label>
          <select id="who" v-model="filters.user_id" class="select">
            <option value="">{{ t('activity.anyone') }}</option>
            <option v-for="person in people" :key="person.id" :value="person.id">{{ person.name }}</option>
          </select>
        </div>
        <div class="field">
          <label for="from">{{ t('reports.from') }}</label>
          <input id="from" v-model="filters.from" class="input num" type="date" />
        </div>
        <div class="field">
          <label for="to">{{ t('reports.to') }}</label>
          <input id="to" v-model="filters.to" class="input num" type="date" />
        </div>
      </div>

      <SkeletonTable v-if="loading" :columns="4" />

      <template v-else>
        <ol v-if="items.length" class="feed">
          <li v-for="entry in items" :key="entry.id" class="entry">
            <span class="dot" :class="tone(entry.action)" />
            <div class="body">
              <p class="summary">{{ entry.summary }}</p>
              <p class="meta muted num">
                {{ when(entry.created_at) }}
                · {{ entry.actor?.name ?? t('activity.system') }}
                <template v-if="entry.ip_address"> · {{ entry.ip_address }}</template>
              </p>
            </div>
            <span class="tag num">{{ label(entry.action) }}</span>
          </li>
        </ol>

        <EmptyState v-else :title="t('activity.empty')" :hint="t('activity.emptyHint')" />
      </template>

      <PagerBar :meta="meta" @page="goToPage" />
    </section>
  </div>
</template>

<style scoped>
.notice { padding: var(--sp-4) var(--sp-5); margin: 0; color: var(--text-muted); font-size: var(--text-base); }
.feed { list-style: none; margin: 0; padding: 0; }
.entry {
  display: grid;
  grid-template-columns: 14px 1fr auto;
  gap: var(--sp-3);
  align-items: start;
  padding: var(--sp-3) var(--sp-5);
  border-bottom: 1px solid var(--line);
}
.entry:last-child { border-bottom: none; }
.dot { width: 8px; height: 8px; margin-top: 6px; border-radius: 50%; }
.dot.negative { background: var(--negative); }
.dot.money { background: var(--money); }
.dot.accent { background: var(--accent); }
.dot.neutral { background: var(--neutral); }
.summary { margin: 0; font-size: var(--text-base); }
.meta { margin: 2px 0 0; font-size: var(--text-xs); }
.tag {
  font-size: 10px;
  letter-spacing: 0.05em;
  color: var(--text-faint);
  white-space: nowrap;
  padding-top: 3px;
}
</style>
