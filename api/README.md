# Rent Management API (Laravel 12 + Sanctum)

A token-authenticated REST API for managing rental properties: properties → units → leases → invoices → payments, plus tenants and maintenance requests.

Every response is JSON, so any client (React/Vue SPA, Flutter, mobile app, Postman) can consume it as-is.

---

## 1. Installation

These files are the **application code only** — drop them into a fresh Laravel install.

```bash
# 1. Create a fresh Laravel 12 app
composer create-project laravel/laravel rent-management
cd rent-management

# 2. Enable API routing + Sanctum (creates routes/api.php and personal_access_tokens migration)
php artisan install:api

# 3. Copy the contents of this package over the app
#    (app/, database/, routes/, tests/ — overwriting routes/api.php)
cp -r /path/to/this/package/app        ./
cp -r /path/to/this/package/database   ./
cp -r /path/to/this/package/routes     ./
cp -r /path/to/this/package/tests      ./

# 4. Database — SQLite is the fastest way to get running
touch database/database.sqlite
```

In `.env`:

```env
DB_CONNECTION=sqlite
# leave the DB_HOST/DB_DATABASE lines commented out for SQLite
```

For MySQL/PostgreSQL instead:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rent_management
DB_USERNAME=root
DB_PASSWORD=
```

Then:

```bash
php artisan migrate --seed
php artisan storage:link   # required — logo uploads are served from here
php artisan serve
```

**`storage:link` is not optional.** It symlinks `public/storage` to `storage/app/public`, which is where uploaded logos live. Skip it and every logo URL returns 404 while the rest of the app works fine — a confusing failure to debug later.

The API is now at `http://127.0.0.1:8000/api`.

**Seeded demo accounts** (all `password`): `demo@example.com` (owner), `manager@example.com`, `agent@example.com`, `viewer@example.com` — sign in as each to see how the interface changes by role.

The owner's account has two properties, seven units, five active leases, three months of invoices and part-paid rent.

### One thing to merge by hand

`routes/console.php` in this package **replaces** the default file (which only contains the `inspire` command). If you want to keep `inspire`, paste the two `Schedule::command(...)` lines into your existing file instead of overwriting it.

---

## 2. Data model

```
User (landlord)
 ├── Property ──── Unit ──── Lease ──── Invoice ──── Payment
 │                   └────── MaintenanceRequest
 └── Tenant ─────────────────┘
```

| Table | Purpose |
|---|---|
| `properties` | A building or plot owned by a landlord |
| `units` | An individually lettable space; carries the asking rent and `vacant`/`occupied`/`maintenance` status |
| `tenants` | People who rent, owned by a landlord (not login accounts) |
| `leases` | Binds a tenant to a unit: dates, agreed rent, deposit, billing day |
| `invoices` | One rent charge per lease per month, with `amount_paid` kept in sync |
| `payments` | Individual receipts against an invoice |
| `maintenance_requests` | Repair tickets on a unit |

**Team accounts.** A portfolio belongs to one *account owner*. Staff users carry `account_owner_id` pointing at that owner and see exactly the same records, filtered by role rather than by ownership. `ownedBy()` scopes resolve to `accountOwnerId()`, so a manager's query and the owner's query return the same rows.

| Role | Abilities |
|---|---|
| `owner` | Everything, including team, settings and deletions |
| `manager` | All operations and reports; not team or settings |
| `agent` | Tenants, leases, invoices, record payments, maintenance — **not** payment reversal, property changes or deletions |
| `accountant` | Invoices, record **and reverse** payments, all reports and the activity log — not letting or admin |
| `viewer` | Read-only across the portfolio and reports |

Reading is open to any active member; abilities gate writes. They're enforced in `authorize()` on the form requests, and via `AuthorizesAbilities::must()` for actions that have no form request. There's no middleware to register — deliberately, to keep setup to `install:api` and nothing else.

Every landlord only ever sees their own rows — each model has an `ownedBy()` scope, and every controller resolves records through it, so a guessed ID from another account returns `404` rather than leaking data.

---

## 3. Authentication

Sanctum bearer tokens. Register or log in, then send the token on every other call:

```
Authorization: Bearer <token>
Accept: application/json
```

```bash
# Register
curl -X POST http://127.0.0.1:8000/api/register \
  -H "Accept: application/json" \
  -d "name=Jane&email=jane@example.com&password=secret123&password_confirmation=secret123"

# Log in
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Accept: application/json" \
  -d "email=demo@example.com&password=password"
```

---

## 4. Endpoints

### Auth
| Method | Path | Notes |
|---|---|---|
| POST | `/api/register` | `name, email, password, password_confirmation, phone?` |
| POST | `/api/login` | Returns `user` + `token` |
| GET | `/api/me` | Current user |
| POST | `/api/logout` | Revokes the current token only |

### Dashboard
| Method | Path | Notes |
|---|---|---|
| GET | `/api/dashboard` | Occupancy rate, billed/collected this month, outstanding and overdue totals, recent payments, overdue invoices |

### Properties
| Method | Path | Notes |
|---|---|---|
| GET | `/api/properties` | `?search=&type=&per_page=` |
| POST | `/api/properties` | `name, type, address, city?, description?` |
| GET | `/api/properties/{id}` | Includes its units |
| PUT/PATCH | `/api/properties/{id}` | |
| DELETE | `/api/properties/{id}` | Blocked while any unit has an active lease |

`type` is one of `apartment`, `house`, `commercial`, `mixed`.

### Units
| Method | Path | Notes |
|---|---|---|
| GET | `/api/units` | `?property_id=&status=&search=` |
| POST | `/api/units` | `property_id, label, rent_amount, bedrooms?, bathrooms?, size_sqm?, deposit_amount?` |
| GET | `/api/units/{id}` | Includes the active lease and its tenant |
| PUT/PATCH | `/api/units/{id}` | |
| DELETE | `/api/units/{id}` | Blocked while a lease is active |

`status` is one of `vacant`, `occupied`, `maintenance` — set automatically when a lease starts or ends.

### Tenants
| Method | Path | Notes |
|---|---|---|
| GET | `/api/tenants` | `?search=` across name, phone, email |
| POST | `/api/tenants` | `first_name, last_name, phone` required; phone unique per landlord |
| GET | `/api/tenants/{id}` | Includes lease history |
| PUT/PATCH | `/api/tenants/{id}` | |
| DELETE | `/api/tenants/{id}` | Blocked while a lease is active |

### Leases
| Method | Path | Notes |
|---|---|---|
| GET | `/api/leases` | `?status=&unit_id=&tenant_id=` |
| POST | `/api/leases` | `unit_id, tenant_id, start_date, end_date?, rent_amount?, deposit_amount?, billing_day?` |
| GET | `/api/leases/{id}` | Includes invoices |
| PUT/PATCH | `/api/leases/{id}` | Rent, deposit, billing day, end date |
| POST | `/api/leases/{id}/terminate` | `terminated_at?` — frees the unit |

`rent_amount` and `deposit_amount` fall back to the unit's figures. `billing_day` is 1–28.

### Invoices
| Method | Path | Notes |
|---|---|---|
| GET | `/api/invoices` | `?status=&lease_id=&overdue=1&from=&to=` |
| GET | `/api/invoices/{id}` | Includes payments |
| POST | `/api/invoices/generate` | `month?` (`YYYY-MM`) — bills **all** active leases |
| POST | `/api/leases/{id}/invoices` | `month?` — bills one lease |
| POST | `/api/invoices/{id}/cancel` | Only while nothing has been paid |

`status` is `unpaid`, `partially_paid`, `paid` or `cancelled`. `is_overdue` is computed from the due date, so it is never stale.

### Payments
| Method | Path | Notes |
|---|---|---|
| GET | `/api/payments` | `?invoice_id=&method=&from=&to=` |
| POST | `/api/invoices/{id}/payments` | `amount, method, paid_at?, reference?, notes?` |
| GET | `/api/payments/{id}` | |
| DELETE | `/api/payments/{id}` | Reverses it and restores the invoice balance |

`method` is `cash`, `bank_transfer`, `mobile_money`, `cheque` or `card`.

### Your own account
| Method | Path | Notes |
|---|---|---|
| GET | `/api/profile` | Your details |
| PUT | `/api/profile` | Name, phone, email — every role, including viewer |
| PUT | `/api/profile/password` | Requires `current_password`; revokes all other tokens |

Role is not accepted here, so nobody can promote themselves. Changing a password signs out every other device, which is the point of changing it.

### Photos and notifications
| Method | Path | Notes |
|---|---|---|
| GET | `/api/units/{unit}/photos` | Photos of a unit, cover first |
| POST | `/api/units/{unit}/photos` | Multipart `photos[]`, up to 10 at once, PNG/JPG/WEBP max 4 MB |
| POST | `/api/units/{unit}/photos/{photo}/cover` | Choose the listing image |
| DELETE | `/api/units/{unit}/photos/{photo}` | Removes the file; promotes another to cover |
| GET | `/api/alerts` | In-app alerts plus unread count |
| POST | `/api/alerts/read-all` · `/api/alerts/{alert}/read` | Mark read |
| GET | `/api/outbox` | Everything queued or sent to tenants |

### Administration
| Method | Path | Notes |
|---|---|---|
| GET | `/api/team` | Everyone on the account, owner first (owner only) |
| GET | `/api/team/roles` | Roles and the abilities each carries |
| POST | `/api/team` | `name, email, role, password` — owner sets the opening password |
| PUT | `/api/team/{member}` | Change role, suspend, reset password |
| DELETE | `/api/team/{member}` | Revokes tokens; records they created stay |
| GET | `/api/settings` | Readable by any member — the client needs currency, locale and branding |
| PUT | `/api/settings` | Owner only |
| POST | `/api/settings/logo` | Multipart `logo` field. PNG/JPG/WEBP, max 1 MB, max 2400px. Owner only |
| DELETE | `/api/settings/logo` | Removes the file and clears the setting |
| GET | `/api/activity` | `?action=&user_id=&from=&to=` — audit trail (manager and above) |

### Reports
| Method | Path | Notes |
|---|---|---|
| GET | `/api/reports/rent-roll` | Contracted income per unit, vacancy loss, arrears, occupancy |
| GET | `/api/reports/arrears` | Debt per tenant bucketed 0/30/60/90+ days |
| GET | `/api/reports/collections` | `?months=12` — billed vs collected per month with rate |
| GET | `/api/reports/vacancy` | Empty units, days vacant, revenue forgone |
| GET | `/api/reports/renewals` | `?days=90` — leases ending inside the window |
| GET | `/api/reports/property-performance` | `?from=&to=` — collections net of upkeep, per property |
| GET | `/api/reports/maintenance` | `?from=&to=` — spend by property, priority, costliest units |
| GET | `/api/reports/tenant-statement/{tenant}` | Running ledger of charges and payments |

All are read-only and scoped to the authenticated landlord. Date ranges default to the last twelve months.

### Maintenance requests
| Method | Path | Notes |
|---|---|---|
| GET | `/api/maintenance-requests` | `?status=&priority=&unit_id=` |
| POST | `/api/maintenance-requests` | `unit_id, title, tenant_id?, description?, priority?` |
| GET/PUT/PATCH/DELETE | `/api/maintenance-requests/{id}` | Setting `status=resolved` stamps `resolved_at` |

---

## 5. Business rules worth knowing

- **Payment terms are a number of months, per lease.** `billing_period_months` is any integer from 1 to 24 — terms get negotiated, so four or seven months is as valid as three. One invoice covers one whole term: a six-month lease at 150,000/month produces a single invoice for 900,000, not six of 150,000. Two tenants in the same building can be on different terms.
- **Every term runs from its own start date.** A lease beginning on the 2nd covers the 2nd to the 1st of the following month, at the full agreed rent. Starting mid-month is not a part period — the tenant agreed a rent per period, not a share of the calendar.
- **Proration happens only when a lease is cut short.** If a three-month term ends after one month, that invoice is prorated by days, because the tenant will not be there. Nothing else prorates. (An earlier version billed calendar months and charged 30/31 of the rent for a lease starting on the 2nd, which was wrong for markets where rent is paid in full for the agreed term.)
- **Rent is due on the first day of the period**, plus an optional grace period (`grace_days` in settings) so a freshly raised invoice is not immediately overdue.
- **The scheduler runs daily and raises only what is due**, so a five-month term starting on the 14th is billed on the 14th, five months apart. `invoices:generate` with no arguments bills every period that has begun and has no invoice yet; `--month` still bills one specific month by hand.
- **Deposits are optional.** `collect_deposit` in settings removes the deposit fields from lease forms for markets that do not use them.
- **Notifications split by audience.** Landlords and staff use the app, so they get in-app alerts (`alerts`). Tenants have no login, so their messages are queued in `outbound_messages` and dispatched separately — nothing is lost if a gateway is down, and there is a record of exactly what was sent. `notifications:run` at 07:00 raises due-soon reminders, weekly overdue chases and lease-expiry warnings, then flushes the queue.
- **The SMS driver is deliberately unimplemented.** `config('services.sms.driver')` defaults to `log`, which writes each message to the log so the whole flow is testable before an SMS contract exists. Wiring a real gateway means one method in `NotificationService::dispatchQueued()`, not rewiring the app.
- **Reminders are deduped by stage, not by day.** An overdue invoice is chased weekly, not every morning, via a `dedupe_key` unique per account.
- **Unit photos** live in `unit_photos` with one cover per unit. Deleting the cover promotes the next photo automatically, and SVG is refused for the same reason as the logo.
- **Invoicing is idempotent.** A unique index on `(lease_id, period_start)` plus a lookup before insert means re-running generation never duplicates a charge — safe to retry, safe to run from cron and by hand on the same day.
- **Partial months are prorated** daily. A lease starting on the 16th of a 30-day month is billed 15/30 of the rent; the same applies to the month a lease is terminated.
- **Payments can't exceed the balance.** The invoice row is locked (`lockForUpdate`) inside a transaction, so two simultaneous payments can't both read a stale balance and overpay.
- **`amount_paid` is recomputed from the payment rows**, never incremented blindly — so reversing a payment always leaves correct totals.
- **A unit can't be let twice.** Opening a lease on a unit that already has an active one returns `422`.
- **Reports aggregate in PHP, not SQL.** Month bucketing differs between MySQL (`DATE_FORMAT`) and SQLite (`strftime`), and a landlord's data volume is small, so portability beats query optimisation here. If a portfolio grows past a few thousand invoices, the collections and performance reports are the first things to push down into SQL.
- **Day arithmetic avoids Carbon's version differences.** `diffInDays` changed signature between Carbon 2 and 3; `ReportService::daysBetween()` uses timestamps so aging buckets can't silently invert.
- **Settings are real, not decorative.** `invoice_prefix` feeds invoice numbering, `auto_invoice` decides whether the scheduled command bills an account, `currency` drives every amount the client renders. Nothing in the settings screen is a placeholder.
- **SVG logos are refused on purpose.** An SVG can carry script, and uploaded files are served from the same origin as the app, so accepting one would hand an owner a stored-XSS vector against their own team. Raster formats only, and the error message says so rather than just failing validation.
- **Replacing a logo deletes the old file.** Uploads don't accumulate orphans in storage; there's a test asserting the previous file is gone.
- **The activity log records money and access, not reads.** Payments recorded and reversed, invoices raised and cancelled, leases started and terminated, records deleted, team and settings changes. It answers "who reversed that payment", which is the question that actually gets asked.
- **A suspended member keeps their password but loses the door.** `is_active` is checked at login and inside `hasPermission()`, so an in-flight token stops working too.
- **Money is `decimal(12,2)`**, not float. Amounts are plain numbers with no currency attached — set your currency in the client, or add a `currency` column if you need multi-currency.

---

## 6. Scheduled jobs

```bash
php artisan invoices:generate                 # current month, all landlords
php artisan invoices:generate --month=2026-06 # a specific month
php artisan invoices:generate --user=1        # one landlord
php artisan leases:expire                     # close leases past their end date
```

Both are wired into the scheduler in `routes/console.php`. Add one cron entry on the server:

```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 7. Tests

```bash
php artisan test
```

`tests/Feature/RentWorkflowTest.php` covers the paths most likely to break: lease creation flipping unit status, double-letting rejection, idempotent invoicing, proration, payment status transitions, overpayment rejection, lease termination, and cross-account isolation.

`tests/Feature/AdministrationTest.php` covers access control: staff seeing the owner's portfolio, an agent taking money but not reversing it, viewers blocked from writing, owner-only team and settings, the owner being undeletable and undemotable, suspended members locked out, the activity log capturing money movements, the invoice prefix setting taking effect, and audit entries never crossing accounts.

`tests/Feature/ReportsTest.php` covers the reports: rent roll separating contracted income from vacancy loss, arrears buckets shrinking when paid, the collections series returning one row per month, vacancy counting only unlet units, property performance netting upkeep against collections, the tenant statement running a correct balance down the page, and reports never leaking another landlord's portfolio.

If you use SQLite for tests, add to `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

---

## 8. Sensible next steps

- **Tenant logins** — tenants are records, not users. Add a `tenant_user_id` and a policy layer if tenants should see their own statements.
- **Late fees** — a `late_fee_amount` on the lease plus a daily command that adds a charge to overdue invoices.
- **PDF receipts and invoices** — `barryvdh/laravel-dompdf` over the existing resources.
- **Payment gateway webhooks** — the mobile-money/card methods are recorded manually today; a webhook controller calling `PaymentService::record()` is all that's needed.
- **Notifications** — rent-due and overdue reminders by SMS/email off the same scheduler.
- **Roles** — one landlord per account today; add a team/role layer if agents will share a portfolio.
