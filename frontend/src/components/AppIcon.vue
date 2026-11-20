<script setup>
/*
 * A small hand-drawn icon set rather than a dependency. Every glyph sits on
 * the same 24px grid with a 1.75 stroke and inherits currentColor, so icons
 * pick up palette tokens exactly like text does.
 */
defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 18 },
})

const PATHS = {
  // --- Navigation ---
  dashboard: '<rect x="3" y="3" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2"/>',
  properties: '<path d="M3 21h18"/><path d="M5 21V6a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v15"/><path d="M15 21V11h3a2 2 0 0 1 2 2v8"/><path d="M8.5 8h3M8.5 12h3M8.5 16h3"/>',
  units: '<rect x="4" y="3" width="13" height="18" rx="2"/><path d="M17 21h3"/><circle cx="13.5" cy="12" r="1"/>',
  tenants: '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.2a3.2 3.2 0 0 1 0 5.9"/><path d="M17.5 14.6A5.6 5.6 0 0 1 21 20"/>',
  leases: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M8.5 13.5h7M8.5 17h4"/>',
  invoices: '<path d="M6 2.5v19l2-1.4 2 1.4 2-1.4 2 1.4 2-1.4 2 1.4v-19l-2 1.4-2-1.4-2 1.4-2-1.4-2 1.4z"/><path d="M9.5 9h5M9.5 13h5"/>',
  payments: '<rect x="2.5" y="5.5" width="19" height="13" rx="2.5"/><path d="M2.5 10h19"/><path d="M6 14.5h3"/>',
  maintenance: '<path d="M14.7 6.3a4.5 4.5 0 0 0 5.9 5.9L21 12l-9 9a2.4 2.4 0 0 1-3.4-3.4l9-9z"/><path d="M8.5 8.5 5 5"/><path d="M3 7.5 7.5 3l3 3-4.5 4.5z"/>',
  reports: '<path d="M3 21h18"/><rect x="5" y="11" width="3.5" height="7" rx="1"/><rect x="10.5" y="6" width="3.5" height="12" rx="1"/><rect x="16" y="14" width="3.5" height="4" rx="1"/>',
  team: '<circle cx="8.5" cy="8" r="3.2"/><path d="M2.5 20a6 6 0 0 1 12 0"/><path d="M17.5 11.5v6M14.5 14.5h6"/>',
  settings: '<path d="M4 7h10M18 7h2"/><path d="M4 12h4M12 12h8"/><path d="M4 17h9M17 17h3"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="15" cy="17" r="2"/>',
  activity: '<path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1"/><path d="M3 4v4h4"/><path d="M12 8v4.5l3 1.8"/>',

  // --- Row actions ---
  edit: '<path d="M4 20h4.5L19 9.5a2.5 2.5 0 0 0-3.5-3.5L5 16.5V20z"/><path d="M14.5 7 17 9.5"/>',
  trash: '<path d="M4 7h16"/><path d="M9.5 7V5.5A1.5 1.5 0 0 1 11 4h2a1.5 1.5 0 0 1 1.5 1.5V7"/><path d="M6.5 7l.8 12a2 2 0 0 0 2 1.9h5.4a2 2 0 0 0 2-1.9l.8-12"/><path d="M10.5 11v6M13.5 11v6"/>',
  undo: '<path d="M4 9h9.5a5.5 5.5 0 0 1 0 11H8"/><path d="M8 4.5 3.5 9 8 13.5"/>',
  check: '<path d="M4.5 12.5 9.5 17.5 19.5 6.5"/>',
  ban: '<circle cx="12" cy="12" r="8.5"/><path d="M6 18 18 6"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  download: '<path d="M12 3.5v11"/><path d="M8 11l4 4 4-4"/><path d="M4 16v2.5A2.5 2.5 0 0 0 6.5 21h11a2.5 2.5 0 0 0 2.5-2.5V16"/>',
  printer: '<path d="M7 9V4h10v5"/><rect x="3.5" y="9" width="17" height="7" rx="2"/><path d="M7 14h10v6H7z"/>',
  refresh: '<path d="M20 11a8 8 0 0 0-13.7-5.2L3.5 8.5"/><path d="M3.5 4v4.5H8"/><path d="M4 13a8 8 0 0 0 13.7 5.2l2.8-2.7"/><path d="M20.5 20v-4.5H16"/>',

  // --- Interface ---
  search: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/>',
  menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
  collapse: '<rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="M9.5 4v16"/>',
  close: '<path d="M6 6l12 12M18 6L6 18"/>',
  sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.2 5.2l1.4 1.4M17.4 17.4l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"/>',
  moon: '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z"/>',
  system: '<rect x="2.5" y="4" width="19" height="12.5" rx="2"/><path d="M9 20.5h6M12 16.5v4"/>',
}
</script>

<template>
  <svg
    class="icon"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.75"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
    v-html="PATHS[name] || ''"
  />
</template>

<style scoped>
.icon { flex: none; display: block; }
</style>
