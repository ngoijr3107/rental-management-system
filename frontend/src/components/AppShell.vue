<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { usePrefsStore } from '@/stores/prefs'
import ThemeToggle from './ThemeToggle.vue'
import LanguageToggle from './LanguageToggle.vue'
import PaletteToggle from './PaletteToggle.vue'
import CommandPalette from './CommandPalette.vue'
import AppIcon from './AppIcon.vue'
import AlertBell from './AlertBell.vue'

/*
 * The sidebar is deliberately self-contained: it draws its own brand rather
 * than nesting a child component, and the few rules that decide whether it is
 * readable at all (the flex row, the column layout) are inline styles. Scoped
 * CSS from a stale bundle cannot override an inline style, and there is no
 * child root node for a parent rule to leak onto.
 */

const auth = useAuthStore()
const prefs = usePrefsStore()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()

const open = ref(false)
const palette = ref(null)

const SECTIONS = [
  { label: 'nav.overview', links: [{ to: '/', key: 'nav.dashboard', icon: 'dashboard', exact: true }] },
  {
    label: 'nav.portfolio',
    links: [
      { to: '/properties', key: 'nav.properties', icon: 'properties' },
      { to: '/units', key: 'nav.units', icon: 'units' },
    ],
  },
  {
    label: 'nav.letting',
    links: [
      { to: '/tenants', key: 'nav.tenants', icon: 'tenants' },
      { to: '/leases', key: 'nav.leases', icon: 'leases' },
    ],
  },
  {
    label: 'nav.money',
    links: [
      { to: '/invoices', key: 'nav.invoices', icon: 'invoices' },
      { to: '/payments', key: 'nav.payments', icon: 'payments' },
    ],
  },
  { label: 'nav.upkeep', links: [{ to: '/maintenance', key: 'nav.maintenance', icon: 'maintenance' }] },
  { label: 'nav.insight', links: [{ to: '/reports', key: 'nav.reports', icon: 'reports' }] },
  {
    label: 'nav.admin',
    links: [
      { to: '/team', key: 'nav.team', icon: 'team', requires: 'users.manage' },
      { to: '/settings', key: 'nav.settings', icon: 'settings' },
      { to: '/activity', key: 'nav.activity', icon: 'activity', requires: 'activity.view' },
    ],
  },
]

/**
 * Sections this member can open. If permission data is missing for any reason,
 * fall back to every non-restricted link rather than rendering an empty rail —
 * a menu that vanishes is far worse than one showing a link that 403s.
 */
const sections = computed(() => {
  let canDo
  try {
    canDo = (ability) => auth.can(ability)
  } catch {
    canDo = () => false
  }

  const visible = SECTIONS.map((section) => ({
    label: section.label,
    links: section.links.filter((link) => !link.requires || canDo(link.requires)),
  })).filter((section) => section.links.length > 0)

  if (visible.length > 0) return visible

  return SECTIONS.map((section) => ({
    label: section.label,
    links: section.links.filter((link) => !link.requires),
  })).filter((section) => section.links.length > 0)
})

const brandName = computed(() => auth.brandName || t('app.name'))
const brandLogo = computed(() => auth.logoUrl)
const brandInitials = computed(() => auth.brandInitials || 'RM')

const userInitials = computed(() => {
  const name = auth.user?.name?.trim()
  if (!name) return '·'
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
})

const currentTitle = computed(() => {
  for (const section of SECTIONS) {
    for (const link of section.links) {
      if (link.exact ? route.path === link.to : route.path.startsWith(link.to) && link.to !== '/') {
        return t(link.key)
      }
    }
  }
  return t('nav.dashboard')
})

function isActive(link) {
  return link.exact
    ? route.path === link.to
    : route.path.startsWith(link.to) && link.to !== '/'
}

async function signOut() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="rm-shell" :class="{ 'is-open': open, 'is-collapsed': prefs.railCollapsed }">
    <aside class="rm-rail" aria-label="Main" style="display: flex; flex-direction: column">
      <!-- Brand: inline flex so it is a row no matter what CSS reaches it -->
      <div
        class="rm-brand"
        style="display: flex; align-items: center; gap: 10px; min-width: 0"
      >
        <img
          v-if="brandLogo"
          :src="brandLogo"
          :alt="brandName"
          class="rm-logo"
          style="width: 28px; height: 28px; flex: none"
        />
        <span
          v-else
          class="rm-chip"
          style="width: 28px; height: 28px; flex: none; display: grid; place-items: center"
        >
          {{ brandInitials }}
        </span>
        <span class="rm-word">{{ brandName }}</span>
      </div>

      <nav class="rm-nav" style="flex: 1 1 auto; overflow-y: auto">
        <div v-for="section in sections" :key="section.label" class="rm-group">
          <p class="rm-group-label">{{ t(section.label) }}</p>
          <RouterLink
            v-for="link in section.links"
            :key="link.to"
            :to="link.to"
            class="rm-link"
            :class="{ 'is-active': isActive(link) }"
            :title="t(link.key)"
            style="display: flex; align-items: center; gap: 11px"
            @click="open = false"
          >
            <span class="rm-bar" />
            <AppIcon :name="link.icon" :size="18" />
            <span class="rm-label">{{ t(link.key) }}</span>
          </RouterLink>
        </div>
      </nav>

      <div class="rm-account" style="flex: none">
        <RouterLink
          to="/profile"
          class="rm-whoami"
          :title="t('profile.title')"
          style="display: flex; align-items: center; gap: 10px; min-width: 0"
          @click="open = false"
        >
          <span
            class="rm-avatar"
            style="width: 30px; height: 30px; flex: none; display: grid; place-items: center"
          >
            {{ userInitials }}
          </span>
          <div style="min-width: 0">
            <p class="rm-me">{{ auth.user?.name || t('nav.signedIn') }}</p>
            <p class="rm-mail">{{ auth.user?.email }}</p>
          </div>
        </RouterLink>
        <div style="display: flex; align-items: center; gap: 8px; margin-top: 10px">
          <span v-if="auth.user?.role" class="rm-role num">{{ t(`status.${auth.user.role}`) }}</span>
          <span style="flex: 1" />
          <button class="rm-out" type="button" @click="signOut">{{ t('nav.signOut') }}</button>
        </div>
      </div>
    </aside>

    <div class="rm-scrim" @click="open = false" />

    <main class="rm-main">
      <header class="rm-top">
        <button class="rm-menu" type="button" :aria-label="t('nav.menu')" @click="open = !open">
          <AppIcon name="menu" :size="18" />
        </button>
        <button
          class="rm-collapse-top"
          type="button"
          :title="t('nav.menu')"
          :aria-label="t('nav.menu')"
          :aria-expanded="!prefs.railCollapsed"
          @click="prefs.toggleRail()"
        >
          <AppIcon name="collapse" :size="18" />
        </button>
        <p class="rm-crumb">{{ currentTitle }}</p>
        <span style="flex: 1" />
        <button class="rm-search" type="button" @click="palette?.show()">
          <AppIcon name="search" :size="15" />
          <span>{{ t('palette.open') }}</span>
          <kbd class="num">⌘K</kbd>
        </button>
        <AlertBell />
        <PaletteToggle />
        <LanguageToggle />
        <ThemeToggle />
      </header>

      <div id="main" class="rm-content" role="region" tabindex="-1">
        <slot />
      </div>
    </main>

    <CommandPalette ref="palette" />
  </div>
</template>

<style scoped>
.rm-shell { display: flex; min-height: 100vh; }

.rm-rail {
  position: fixed;
  inset: var(--gutter, 12px) auto var(--gutter, 12px) var(--gutter, 12px);
  z-index: 40;
  width: var(--sidebar-w, 244px);
  background: var(--sidebar-bg, #1c1725);
  color: var(--sidebar-text, #cfc7d8);
  padding: 20px 0 16px;
  border-radius: var(--radius-lg, 20px);
  box-shadow: var(--shadow-2);
}

.rm-brand { padding: 0 16px 18px; }

.rm-collapse-top {
  display: grid;
  place-items: center;
  cursor: pointer;
  transition: background 0.16s ease, color 0.16s ease, border-color 0.16s ease;
}

.rm-logo {
  border-radius: 7px;
  object-fit: contain;
  background: var(--surface, #fff);
  padding: 2px;
}

.rm-chip {
  border-radius: 8px;
  background: var(--accent, #5e3a73);
  color: var(--accent-contrast, #fff);
  font-family: var(--mono, monospace);
  font-size: 11px;
  font-weight: 600;
}

.rm-word {
  font-family: var(--display, sans-serif);
  font-size: 14px;
  font-weight: 600;
  color: var(--sidebar-text-strong, #fff);
  letter-spacing: -0.01em;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rm-nav { padding: 0 10px; }
.rm-group { margin-bottom: 16px; }

.rm-group-label {
  margin: 0 0 5px;
  padding: 0 8px;
  font-family: var(--mono, monospace);
  font-size: 9px;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--sidebar-text-faint, #7b7089);
}

.rm-link {
  position: relative;
  padding: 9px 10px 9px 12px;
  border-radius: var(--radius-sm, 10px);
  font-size: 13px;
  color: var(--sidebar-text, #cfc7d8);
  text-decoration: none;
  transition: background 0.16s ease, color 0.16s ease;
}

.rm-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.rm-link .icon { opacity: 0.75; transition: opacity 0.16s ease; }
.rm-link:hover .icon,
.rm-link.is-active .icon { opacity: 1; }

.rm-bar {
  position: absolute;
  left: -2px;
  top: 50%;
  width: 2px;
  height: 0;
  border-radius: 2px;
  background: var(--accent, #5e3a73);
  transform: translateY(-50%);
  transition: height 0.2s ease;
}

.rm-link:hover { background: var(--sidebar-hover, #2a2334); color: var(--sidebar-text-strong, #fff); }
.rm-link.is-active {
  background: var(--sidebar-hover, #2a2334);
  color: var(--sidebar-text-strong, #fff);
  font-weight: 500;
}
.rm-link.is-active .rm-bar { height: 16px; }

.rm-account {
  padding: 14px 12px 2px;
  margin: 0 10px;
  border-top: 1px solid var(--sidebar-line, #3b3247);
}
.rm-whoami {
  border-radius: var(--radius-sm, 10px);
  padding: 4px;
  margin: -4px;
  text-decoration: none;
  transition: background 0.16s ease;
}
.rm-whoami:hover { background: var(--sidebar-hover, #2a2334); }

.rm-avatar {
  border-radius: 50%;
  background: var(--sidebar-hover, #2a2334);
  color: var(--sidebar-text-strong, #fff);
  font-family: var(--mono, monospace);
  font-size: 11px;
  font-weight: 600;
}
.rm-me { margin: 0; font-size: 13px; font-weight: 500; color: var(--sidebar-text-strong, #fff); }
.rm-mail {
  margin: 0;
  font-size: 11px;
  color: var(--sidebar-text-faint, #7b7089);
  overflow: hidden;
  text-overflow: ellipsis;
}
.rm-role {
  margin: 0;
  padding: 2px 8px;
  border-radius: var(--radius-pill, 999px);
  background: var(--sidebar-hover, #2a2334);
  font-size: 9px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--sidebar-text-faint, #7b7089);
}
.rm-out {
  border: 0;
  background: none;
  padding: 0;
  color: var(--sidebar-text, #cfc7d8);
  font-family: var(--body, sans-serif);
  font-size: 12px;
  cursor: pointer;
}
.rm-out:hover { color: var(--sidebar-text-strong, #fff); text-decoration: underline; }

.rm-main {
  flex: 1;
  margin-left: calc(var(--sidebar-w, 244px) + var(--gutter, 12px) * 2);
  min-width: 0;
  transition: margin-left 0.22s var(--ease, ease);
}

/* --- Collapsed rail: icons only, labels as tooltips ------------------ */
.rm-shell.is-collapsed { --sidebar-w: 72px; }
.rm-shell.is-collapsed .rm-word,
.rm-shell.is-collapsed .rm-label,
.rm-shell.is-collapsed .rm-group-label,
.rm-shell.is-collapsed .rm-me,
.rm-shell.is-collapsed .rm-mail,
.rm-shell.is-collapsed .rm-role,
.rm-shell.is-collapsed .rm-out {
  display: none;
}
.rm-shell.is-collapsed .rm-brand { justify-content: center; padding: 0 0 18px; }
.rm-shell.is-collapsed .rm-link { justify-content: center; padding: 10px; gap: 0; }
.rm-shell.is-collapsed .rm-group { margin-bottom: 10px; }
.rm-shell.is-collapsed .rm-group + .rm-group { border-top: 1px solid var(--sidebar-line, #3b3247); padding-top: 10px; }
.rm-shell.is-collapsed .rm-account { display: flex; justify-content: center; padding: 14px 0 2px; }
.rm-rail { transition: width 0.22s var(--ease, ease); }

.rm-top {
  position: sticky;
  top: 0;
  z-index: 20;
  display: flex;
  align-items: center;
  gap: 8px;
  height: 54px;
  padding: 0 var(--sp-6, 24px);
  border-bottom: 1px solid var(--line);
  background: var(--bg);
  background: color-mix(in srgb, var(--bg) 78%, transparent);
  backdrop-filter: blur(10px);
}

.rm-crumb {
  margin: 0;
  font-family: var(--display, sans-serif);
  font-size: 14px;
  font-weight: 600;
  letter-spacing: -0.01em;
}

.rm-search {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 34px;
  padding: 0 10px 0 14px;
  border: 1px solid var(--line);
  border-radius: var(--radius-pill, 999px);
  background: var(--surface);
  color: var(--text-faint);
  font-family: var(--body, sans-serif);
  font-size: 12px;
  cursor: pointer;
  transition: border-color 0.16s ease, color 0.16s ease;
}
.rm-search:hover { border-color: var(--accent); color: var(--accent-ink); }
.rm-search kbd { padding: 2px 5px; border-radius: 4px; background: var(--surface-3); font-size: 10px; }

.rm-content { padding: var(--sp-6, 24px) var(--sp-6, 24px) 72px; max-width: 1240px; }

.rm-menu { display: none; }
.rm-scrim { display: none; }

.rm-collapse-top {
  color: var(--text-muted);
  border: 1px solid var(--line);
  background: var(--surface);
  width: 34px;
  height: 34px;
  border-radius: var(--radius-sm, 10px);
}
.rm-collapse-top:hover { border-color: var(--accent); color: var(--accent-ink); }

/* Below ~560px the palette swatches are the first control to go — they are a
   preference, not a task, and the search and theme toggles matter more. */
@media (max-width: 560px) {
  .rm-top :deep(.swatches) { display: none; }
  .rm-crumb { font-size: 13px; }
  .rm-search { padding: 0 10px; }
}

@media (max-width: 900px) {
  .rm-rail { transform: translateX(calc(-100% - var(--gutter, 12px))); transition: transform 0.24s ease; }
  .is-open .rm-rail { transform: translateX(0); }
  .rm-main { margin-left: 0; }
  .rm-top { padding: 0 var(--sp-4, 16px); gap: 6px; }
  .rm-content { padding: var(--sp-4, 16px) var(--sp-4, 16px) 56px; }
  /* The rail overlays the page on small screens, so it can be wider. */
  .rm-rail { width: min(84vw, 300px); }
  .rm-search span { display: none; }
  .rm-collapse-top { display: none; }
  .rm-menu {
    display: inline-flex;
    height: 32px;
    width: 32px;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--line);
    border-radius: 7px;
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    font-size: 14px;
  }
  .is-open .rm-scrim {
    display: block;
    position: fixed;
    inset: 0;
    z-index: 30;
    background: var(--scrim, rgba(0, 0, 0, 0.45));
  }
}
</style>
