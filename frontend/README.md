# Rent Manager — web interface

A Vue 3 single-page app for the rent management API. Vite, Vue Router, Pinia and Axios; no UI framework, so the styling is all in one token file you can repaint.

---

## 1. Run it

The Laravel API needs to be running first:

```bash
cd ../rent-management && php artisan serve      # http://127.0.0.1:8000
```

Then:

```bash
npm install
cp .env.example .env
npm run dev                                     # http://localhost:5173
```

Vite proxies `/api` straight to `127.0.0.1:8000`, so there's no CORS setup in development.

Sign in with the seeded account: **demo@example.com** / **password**.

### Production build

```bash
npm run build      # → dist/
npm run preview    # serve dist/ locally to check it
```

Set the real API URL before building:

```env
VITE_API_BASE_URL=https://api.yourdomain.com/api
VITE_CURRENCY=USD
```

If the API ends up on a different origin from the app, the proxy no longer applies and Laravel has to allow the browser through:

```bash
php artisan config:publish cors
# then add your frontend origin to allowed_origins in config/cors.php
```

Routing uses hash URLs (`/#/invoices`), so `dist/` can be dropped on any static host — Netlify, S3, nginx, a `public/app` folder — with no rewrite rules.

---

## 2. What's on screen

| Screen | What it does |
|---|---|
| **Dashboard** | The board, plus collected/outstanding/overdue for the month, arrears to chase and the latest payments. One button raises the whole month's rent. |
| **Properties** | List with search and type filter, add/edit/delete, and a detail page showing the property's units and monthly rent roll. |
| **Units** | Filter by property and status, add/edit/delete, see the sitting tenant, jump to their lease. |
| **Tenants** | Search by name, phone or email; full contact and emergency details. |
| **Leases** | Filter by status, start a lease against any vacant unit, edit terms, terminate. |
| **Lease detail** | Terms, deposit held, and the full rent ledger for that tenant with outstanding totals. Bill a single month from here. |
| **Invoices** | Filter by status, overdue, and period. Raise invoices for any month. |
| **Invoice detail** | Outstanding balance, days overdue, who it's for, every payment against it. Record a payment or cancel the invoice. |
| **Payments** | Filter by method and date, with a running total for the page. Reverse a payment recorded in error. |
| **Maintenance** | Filter by status and priority, log a repair against a unit, one-click resolve, record what it cost. |
| **Team** | Add staff, set their role, suspend or remove them, reset a password. Owner only. |
| **Settings** | Logo upload, business identity, currency, invoice prefix, default billing day, automatic billing, default language. Owner edits; everyone else sees it read-only. |
| **Activity log** | Filterable audit feed — money moving, access changing, records deleted. |
| **Reports** | Eight business reports as tabs — rent roll, arrears aging, collections trend, vacancy, renewals, property performance, maintenance spend, tenant statement. Every one exports to CSV and prints cleanly. |

Everything the API exposes has a screen — including the awkward ones like reversing a payment and cancelling an invoice, which are the operations people actually need when they mistype an amount.

---

## 3. The board

The dashboard leads with a tile per unit rather than a row of statistics, because that's how a landlord holds a portfolio in their head: which doors are let, and which ones owe money.

- **Green** — let, rent up to date
- **Red** — let, has an overdue invoice
- **Grey** — vacant
- **Amber** — under works

Clicking a let unit opens its lease; clicking a vacant one takes you to the units list for that property. It also appears on each property's own page.

---

## 4. Roles and administration

Four roles: **owner**, **manager**, **agent**, **viewer**. `/me` returns the member's ability list, the auth store exposes `auth.can('payments.reverse')`, and the UI uses it to hide what the API would refuse anyway — the Reverse button, the delete actions, the whole Administration nav group. Route guards send a member who types a forbidden URL back to the dashboard rather than into a 403 screen.

**This is convenience, not security.** Every one of these abilities is enforced server-side; hiding a button only stops people bumping into errors.

The **currency** now comes from account settings rather than the build-time `VITE_CURRENCY`, which stays as a fallback for the moment before settings load. Changing currency in Settings repaints every amount in the app without a rebuild.

## 5. Branding

Settings → **Logo and name** controls how the app identifies itself.

- **Business name** replaces "Rent Manager" in the sidebar, the browser tab title, and printed report letterheads. Leave it blank and the product name returns.
- **Logo** appears beside the name in the sidebar and at the top of anything printed. PNG, JPG or WEBP up to 1 MB. With no logo, a chip of the business's initials is shown instead — so *Demo Property Co.* renders as `DP`.

`BrandMark.vue` is the single component behind all of it: logo when one exists, initials chip otherwise, with a `sidebar` variant for the dark panel. Adding branding to a new screen is one import.

**The sign-in screen stays generic.** Settings live behind the token, so before anyone authenticates the app has no idea which account is being visited and cannot show its logo. Branding it would need a public endpoint keyed to a subdomain or account slug — worth doing if you ever go multi-tenant on domains, but it isn't there today.

Printed reports get a letterhead — logo, business name, phone, email and address — that exists only on paper via a `.print-only` class.

## 6. Reports

`/reports` carries eight tabs, each answering a question that changes a decision:

| Report | Question |
|---|---|
| Rent roll | What is contracted income, and what is vacancy costing per month? |
| Arrears aging | Who owes, and how stale is it? Bars show the 0/30/60/90+ profile |
| Collections trend | Is the collection rate improving? Inline SVG chart, billed vs received |
| Vacancy | Which doors are empty, how long, and what has that cost? |
| Renewals | Whose lease ends inside 30/60/90 days? |
| Property performance | Which building nets most after upkeep? |
| Maintenance spend | Where does repair money go, and which units keep breaking? |
| Tenant statement | A printable ledger with a running balance |

**Export.** Every report has **Export CSV**, generated in the browser from data already loaded — no server round-trip, and it keeps working if the API blips. A UTF-8 BOM is prepended so Excel doesn't mangle non-ASCII names.

**Payment terms.** `BillingPeriodPicker.vue` is a number of months with common terms as one-tap chips. The number field is always present and always editable — the chips fill it in rather than replacing it. What is typed is kept separate from what is committed, so clearing the box leaves it cleared while you type the next number, and it is only normalised on blur. Seven tests cover it, including clearing the field and typing a value that happens to match a chip.

**Row actions.** Table actions use `RowAction.vue` rather than bare buttons: an icon carries the verb, a `tone` carries the consequence (`danger` turns red only on hover, `positive` green), and the label collapses to a tooltip under 900px while staying in the accessible name at every width. Two tests enforce it — every `<RowAction>` must have an icon and a label, and no bare quiet button may remain in a table row.

**Responsive.** Every table sits in a `.table-scroll` container: the table keeps its own width so columns stay aligned, and the container scrolls with a faded right edge to signal there is more. Below 620px filters stack to full width, page headers put actions on their own row, dialogs become bottom sheets, and the palette swatches drop out of the top bar. There is a test asserting no table escapes its wrapper.

**Print.** **Print** uses a dedicated `@media print` block that forces the light palette, drops the sidebar, top bar, tabs and filters, and keeps table headers repeating across pages. Printing to PDF from the browser gives a clean tenant statement to hand over or attach to an email.

**The chart has no dependency.** The collections chart is SVG generated from computed geometry, so there's no charting library in the bundle and it inherits theme tokens like everything else.

Clicking a debtor's name in Arrears jumps straight to their statement.

## 7. Palettes, dark mode and language

### Three palettes, two modes

The swatch dots in the top bar switch palette; the ☀/☾/◐ button switches light, dark, or follow-system. Both are saved per device and applied before first paint, so there's no flash of the wrong theme on reload.

| Palette | Sidebar | Action colour | Suits |
|---|---|---|---|
| **Corporate Trust** (default) | slate navy `#1E293B` | sky `#0EA5E9` | Enterprise portfolios, accounting-heavy use |
| **Eco Living** | forest `#14532D` | emerald `#10B981` | Residential leasing, suburban complexes |
| **Studio** | aubergine `#1C1725` | plum `#5E3A73` | The original house palette |

All six combinations live in `src/assets/styles.css` as blocks keyed on `[data-palette][data-theme]`. No component hardcodes a colour, so adding a fourth palette is one CSS block plus an entry in `PALETTES` in `src/stores/prefs.js`.

### Liquid glass

Surfaces are translucent with a `backdrop-filter` blur, over a fixed ambient wash of three soft colour fields so there is something behind the glass to see. Cards, the rail, the top bar, dialogs, the command palette, toasts and the segmented controls all sit on it.

The alphas are measured, not eyeballed. Compositing each surface over the page and re-checking contrast shows cards still clear 4.5:1 on every palette at **62%** opaque, so they run at 72% for headroom. The rail needed more: a dark panel over a light page washes out fast, and seven sidebar text tokens were lifted to hold 4.5:1 once translucent.

Two surfaces stay deliberately opaque — the accent hero metric, whose job is to be the loudest thing on screen, and the board tiles, which carry their own state colours.

Glass turns off completely for `@supports not (backdrop-filter)`, for `prefers-reduced-transparency: reduce`, and in print.

**Note the tripled class selectors** (`.card.card.card`) in the glass layer. A scoped component rule compiles to `.card[data-v-hash]` — specificity (0,2,0) — which beats a plain `.card`. Tripling lifts the theme layer to (0,3,0) so it wins regardless of chunk load order, while still losing to a deliberate two-class override.

### Contrast is verified, not assumed

Every text-on-background pair in all six combinations was measured against WCAG relative luminance: **78 pairs, all at or above 4.5:1**, lowest 4.50. Two things that measurement forced:

- **Action colours can't be used directly as text.** Sky `#0EA5E9` on white is 2.77:1 and emerald `#10B981` is 2.43:1 — both well under AA. So `--accent` is the *fill* colour for buttons and dots, and a darkened `--accent-ink` (`#0A77A8`, `#0B7E58`) carries links and text. In dark mode the brightened variants already pass, so ink and fill are the same value.
- **Filled buttons take dark labels, not white.** White on sky is 2.77:1; near-black on sky is 6.44:1. `--accent-contrast` resolves per palette.

The same applies to status tokens. In light mode all four fail as text on white — success `#22C55E` is 2.28:1, warning `#EAB308` is 1.92:1. The specified hex is kept for dots, bars and tile borders where contrast rules don't bind, and a hue-matched darker ink carries the pill text. Dark-mode status hexes pass as-is and are used unchanged.

Status meaning is fixed across palettes: success green, warning amber, danger red, informational blue. Only luminance shifts between modes.

### One deliberate departure

The brief suggested deriving muted text with `opacity: 0.7` on the primary colour rather than a separate hex. This build uses the explicit secondary hexes instead, for three reasons: opacity applies to the whole element including its borders and any child backgrounds, not just the glyphs; a translucent colour composites differently over cards, table headers and washes, so one value yields several unpredictable ratios; and a measured contrast figure becomes impossible to state. The palettes already specify secondary text hexes — `#475569`, `#94A3B8`, `#52525B`, `#A1A1AA` — and those are used directly.

`--text-faint`, used for eyebrows and table headers, is set equal to the secondary text colour rather than being a third dimmer tier. Those are real text at 10px, so they need the full 4.5:1; the visual hierarchy comes from size, weight and letter-spacing instead.

### English and Kiswahili

The `EN / SW` switch sits beside the theme controls. Translations live in `src/i18n/en.js` and `src/i18n/sw.js` — 363 keys each, verified to have identical key sets and identical interpolation placeholders.

Language affects more than labels: dates and currency reformat through `Intl` using `en-GB` or `sw-TZ`, and counts use real pluralisation (`{count} invoice past due | {count} invoices past due`) rather than an "(s)" suffix. Status values from the API — `partially_paid`, `mobile_money`, `in_progress` — are translated at render time by `StatusPill`, so the API keeps returning English enum values and only the display changes.

To add a third language: copy `en.js`, translate the values, and register it in `src/i18n/index.js`.

### Command palette

`⌘K` (or `Ctrl+K`) opens a palette for jumping between screens and flipping theme or language without reaching for the mouse.

## 8. How it's put together

```
src/
├── api/
│   ├── client.js        Axios instance, bearer token, 401 handling, error parsing
│   └── resources.js     One object per API resource — the only place URLs appear
├── i18n/
│   ├── index.js         vue-i18n setup and the locale list
│   ├── en.js / sw.js    Every user-facing string
├── stores/
│   ├── auth.js          Token, user, role abilities and account settings
│   ├── prefs.js         Palette, theme and locale, persisted and applied to <html>
│   └── toast.js         Transient confirmations
├── composables/
│   ├── format.js        Money and dates, formatted for the active locale
│   ├── useCountUp.js    Figure animation, skipped under reduced-motion
│   ├── useCsv.js        Client-side CSV export and print
│   └── useCollection.js Paginated list + debounced filters, shared by every list screen
├── components/          Shell, modal, confirm, pager, pill, empty state, the board
│   └── reports/         One component per report, plus a shared shell and stat strip
└── views/               One per route
```

Two things worth knowing if you extend it:

- **Validation errors bind to fields.** Laravel's 422 body is passed straight into `FormField`, so a rejected `billing_day` shows its message under that input rather than as a toast. Any new form gets this by using the same component.
- **`useCollection` owns list behaviour.** Pass it a `list` function and initial filters; it handles pagination, debounces filter changes and resets to page 1. New list screens shouldn't need their own fetching code.
- **No string literals in views.** Every visible word goes through `t()`. If you add a screen, add its keys to both locale files — the app falls back to English for a missing key, which hides the bug rather than showing it.

---

## 9. Not built yet

- **Coverage is thin.** `npm test` runs 11 checks: seven mount the app shell (nav links, icons, collapse, role filtering, the never-empty-rail guard) and six are source conventions (every table wrapped for scrolling, no hardcoded colours, locale key and placeholder parity, every static `t()` key resolvable). Views and composables have none yet — those are next.
- **Server messages stay English.** Laravel's validation errors are shown verbatim, so a rejected field reads in English even in Kiswahili mode. The client already sends `Accept-Language`; publishing Laravel's `lang/sw` files would close the gap.
- **Amounts assume one currency** set at build time. Multi-currency needs a field on the property or lease and a formatter that reads it.
- **The board loads the first 100 units.** Fine for a small portfolio; past that it needs its own paginated endpoint.
- **No invitation emails.** No mail driver is assumed, so an owner sets a new member's opening password and passes it on. Swapping this for an invitation link is a small change once mail is configured.
- **No tenant-facing view.** The API has no tenant login, so this app is landlord-only.
