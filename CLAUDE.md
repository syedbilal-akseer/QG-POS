# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this app is

A Laravel 11 internal portal / mobile API for Quadri Group ("QG POS"). It is NOT a generic point-of-sale — it is a portal that bridges a local **MySQL** application database with the company's **Oracle EBS** instance. Most business data (customers, items, prices, orders, receipts, banks, warehouses) is mirrored into MySQL via scheduled `sync:oracle-*` commands, edited / approved in the portal, then pushed back into Oracle interface tables.

Three frontends share this backend:
- **Filament-flavored admin/dashboard** built with **Livewire 3** (Blade + Tailwind, not Vue/React).
- **Mobile app API** under `/api/*` authenticated via Sanctum bearer tokens.
- **WhatsApp + FCM** notifications for invoices, receipts, push reminders.

## Common commands

```powershell
# PHP
php artisan serve                            # local dev server
php artisan migrate                          # run migrations against MySQL
php artisan migrate:fresh --seed             # rebuild local DB
php artisan tinker
composer dump-autoload                       # after adding helpers in app/Helpers/

# Frontend (Vite + Tailwind, Livewire-driven, no JS framework)
npm run dev                                  # vite dev server with HMR
npm run build                                # production build

# Tests (Pest)
./vendor/bin/pest                            # full suite
./vendor/bin/pest tests/Feature/Foo.php      # single file
./vendor/bin/pest --filter=test_name         # single test
php artisan test                             # same via artisan

# Linting
./vendor/bin/pint                            # Laravel Pint (PSR-12 style)

# Oracle sync — these are also wired into the scheduler (bootstrap/app.php)
php artisan sync:oracle-customers            # + -clear variant invalidates caches
php artisan sync:oracle-products
php artisan sync:oracle-items-price
php artisan sync:oracle-warehouses
php artisan sync:oracle-order-types
php artisan sync:oracle-banks
php artisan sync:oracle-transporters
php artisan sync:oracle-users
php artisan orders:sync-oracle               # local Orders → Oracle interface tables

# Other operational commands
php artisan list-oracle-views
php artisan diagnose-item-price
php artisan check-whatsapp-config
php artisan test-fcm
php artisan test-powerbi-connection
php artisan send-pending-push-reminders
php artisan wipe-test-user-data
```

The Windows host runs the schedule via a public token route at `/run-commands?token=...` rather than a real cron — see [routes/web.php](routes/web.php). Treat that route as production-critical.

## Architecture

### Two databases, one app

- `mysql` (default) — local portal DB `qg_pos` / `qg_pos_tw`. Contains Users, Roles, local Customers/Items mirrors, Orders, Receipts, MonthlyTourPlans, Visits, Invoices, WMS (GRN/LPN/Locations), CustomerForms, etc.
- `oracle` — connects to Oracle EBS (`yajra/laravel-oci8`). Configured in [config/database.php](config/database.php#L65-L79). All `OracleX` models live in [app/Models/](app/Models/) and explicitly set `$connection = 'oracle'`. Sessions touching Oracle should set NLS via the [OracleNlsSession trait](app/Traits/OracleNlsSession.php) before issuing queries that rely on EBS implicit conversions.

Sync commands pull from Oracle into MySQL on a schedule; "push" flows (orders, receipts, prices) insert into Oracle `*_iface_all` interface tables which a downstream Oracle concurrent program promotes.

### Authorization model — roles + OU scoping

Authorization is more complex than the role enum suggests; read this section before touching any route or list query.

1. **Primary role** — single string on `users.role` (also a relationship via `role_id`). Canonical list in [app/Enums/RoleEnum.php](app/Enums/RoleEnum.php): `admin`, `supply-chain`, `user` (= salesperson), `line-manager`, `hod`, `account-user`, `sales-head`, `price-uploads`, `cmd-khi`, `cmd-lhr`, `scm-lhr`, `invoice-manager`, plus `inventory-manager` used in routes.
2. **Additional roles** — JSON array on `users.additional_roles` (added 2026-05-13). Common values: `view-khi`, `view-lhr`, `view-all`. These are **read-only** and additive — they grant list/show access scoped by location but never write.
3. **Email overrides** — `App\Models\User::isSalesHead()` and `salesLeadEmailOuOverride()` hard-code four named sales-leads (Masood, Nauman, Asim, Fahim) to specific OU lists regardless of their stored role. **Do not "clean this up" without checking the comments** — these overrides exist because the role column can be wrong for those four users.
4. **OU scoping** — Karachi OUs are `[102,103,104,105,106]`, Lahore OUs are `[108,109]`. Every list query that displays customers/orders/receipts/invoices must filter by `getEffectiveOuIds()` (read-side, includes view-* roles) or `getAllowedOuIds()` / `getAllowedReceiptOuIds()` (write-side).

The `checkRole:roleA,roleB` middleware (alias in [bootstrap/app.php](bootstrap/app.php)) checks the primary role, then `additional_roles`, then `is{Role}()` email overrides, then a privileged-roles allowlist. See [app/Http/Middleware/CheckRole.php](app/Http/Middleware/CheckRole.php).

For Livewire components and controllers that should be write-blocked when a user got access via a view-* additional role only, use the [ViewRoleGuard trait](app/Traits/ViewRoleGuard.php) and call `$this->blockIfViewOnly()` at the top of every write action.

### Routing & landing redirects

[routes/web.php](routes/web.php) at `/` does role-based landing redirects (sales-head → dashboard, salesperson → monthly tour plans, scm-lhr → Lahore orders, inventory-manager → WMS locations, invoice-manager → invoices, supply-chain → supply-chain orders). When adding a new role, **also add it to the redirect block at the top of routes/web.php** or users will hit the "fall through to dashboard" branch and may redirect-loop. The dashboard route itself gates `invoice-manager` out for exactly this reason.

Routes are organised by role-gated `Route::middleware(['checkRole:...'])->group(...)` blocks. The same module (e.g. price-lists) frequently appears under multiple role groups; keep them in sync or read-only roles will get half a feature.

API routes (`/api/*`) are Sanctum-authenticated and live in [routes/api.php](routes/api.php). The mobile app expects POST for most "search"/"get" endpoints even when GET would be more natural — preserve verbs.

### Livewire layout

- Full-page Livewire components are mounted directly as routes (e.g. `Route::get('/orders', ListOrders::class)`).
- [app/Livewire/](app/Livewire/) holds list components (`ListOrders`, `ListCustomers`, etc.), plus subtrees: `CRM/` (sales-head workflows), `WMS/` (warehouse), `Inventory/`, `Sidebar/`, `Widgets/`.
- Vite refreshes on `app/Livewire/**` (see [vite.config.js](vite.config.js)). Use Alpine.js for tiny client-side interactions; no SPA framework.
- Filament is used **only for its tables, widgets, and notification packages** — not as a full Filament panel. `notify(...)` is a global helper wrapping Filament notifications, registered in [app/Helpers/helpers.php](app/Helpers/helpers.php).

### Notification side-channels

- **WhatsApp Cloud API** — [app/Services/WhatsAppService.php](app/Services/WhatsAppService.php) plus [config/whatsapp.php](config/whatsapp.php). Used for invoice delivery and customer form sharing; has a queue-status endpoint surfaced to admins.
- **FCM** — [app/Services/FcmService.php](app/Services/FcmService.php), [config/firebase.php](config/firebase.php). Tokens registered via `/api/profile/fcm-token` (mobile, Sanctum) and `/fcm-token` (web portal, session) — both paths must keep `fcm_token` + `fcm_token_updated_at` in sync.

### PDF generation

Multiple PDF libraries are loaded — they are **not interchangeable**:
- `tcpdf` — WMS LPN labels, QR sheets, pick slips (see `App\Http\Controllers\WMS\Wms*Controller`).
- `dompdf` — invoices and receipts that use Blade templates.
- `fpdf` / `fpdi` — overlay/merge flows for pre-printed forms.
- `smalot/pdfparser` — reading uploaded Oracle invoices.

Pick the library that the surrounding code in the same module already uses; do not introduce a new one.

### Composer autoload quirks

`composer.json` autoloads two helper files globally: [app/Helpers/helpers.php](app/Helpers/helpers.php) and [app/Helpers/transform.php](app/Helpers/transform.php). After editing either, run `composer dump-autoload`. Helpers defined there (e.g. `notify`, `isManager`, `isHod`, `formatOrderItems`) are available everywhere — use them rather than reimplementing.

### Testing notes

- Test framework is **Pest** (with `pestphp/pest-plugin-laravel`), not vanilla PHPUnit.
- [phpunit.xml](phpunit.xml) deliberately leaves `DB_CONNECTION` commented out — tests run against the configured MySQL, **not** an in-memory SQLite. If you add Oracle-dependent tests, mock the connection; CI does not have an Oracle reachable.
- `BCRYPT_ROUNDS=4` and `QUEUE_CONNECTION=sync` are set for test speed.

### Things to be careful about

- **Path traversal**: the `/invoices/{path}` route in [routes/web.php](routes/web.php) serves PDFs from `storage/app/invoices` and explicitly guards against `..` via `realpath`. Preserve those checks if you touch it.
- **CSRF exemptions**: `app/admin/price-lists/enter-new-prices` is excluded from CSRF (see [bootstrap/app.php](bootstrap/app.php)) because Oracle posts back to it. Don't add more exemptions without a similar reason.
- **The `/testing` and `/run-commands` routes** are diagnostic/operational endpoints. `/run-commands` is gated by a hardcoded token string — if you regenerate the token, update the Windows scheduled task that calls it.
- **Static vs parameterised routes**: WMS pick-slip and customer-receipts API routes have a comment reminding that static routes must come before `{id}` routes — preserve that ordering.
- **`additional_roles` migration is recent (2026-05-13)**: old data has nulls; always go through `User::getAdditionalRoles()` rather than reading the column directly.
