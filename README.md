# HappyKoop — Backend API

Laravel 12 REST API for the HappyKoop dropshipping storefront. This is the **only**
layer permitted to touch MySQL. The Next.js frontend lives in a separate repository
([happy-koop-front](https://github.com/Younas22/happy-koop-front)) and reaches the data
only through this API.

- **API contract:** [`docs/api-contract.md`](docs/api-contract.md) — authoritative. Read it
  before changing any response shape.
- **Base path:** `/api/v1`
- **Local origin:** `http://127.0.0.1:8000`

---

## Requirements

| | Version used here | Notes |
|---|---|---|
| PHP | 8.2.12 | 8.2+ required. Laravel 13 needs 8.3+, which is why this is on Laravel 12. |
| MySQL / MariaDB | MariaDB 10.4.32 | No `FULLTEXT` indexes and no `renameColumn` are used, for 10.4 compatibility. |
| Composer | 2.8.8 | |
| Extensions | `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `tokenizer`, `ctype`, `json`, `bcmath`, `gd`, `zip`, `curl`, `exif`, `pdo_sqlite` | `gd` is needed for thumbnails, `pdo_sqlite` for the test suite. |

> `intl` is **not** required and must not be assumed. Currency formatting is done from an
> explicit symbol/exponent table in `app/Support/`, not `NumberFormatter`.

---

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Point `.env` at the existing database (it is **not** created by this project):

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=happykoop
DB_USERNAME=root
DB_PASSWORD=
```

Then:

```bash
php artisan migrate        # additive only - see the warning below
php artisan storage:link   # exposes storage/app/public at /storage
php artisan serve --host=127.0.0.1 --port=8000
```

### Database safety

> **Never run `migrate:fresh`, `migrate:reset`, `db:wipe`, or `TRUNCATE` against
> `happykoop`.** The database is pre-existing and shared. Only `php artisan migrate`
> is used, every migration is additive, and every migration has a working `down()`.

The test suite is immune to this by construction: `phpunit.xml` pins
`DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`, so `RefreshDatabase` can never reach
the real database. **Do not remove those two lines.**

### Seeding demo data (development only)

```bash
php artisan db:seed
```

The seeder is **idempotent** (`firstOrCreate`/`updateOrCreate`, never `truncate`) and
refuses to run in production. It seeds roles, default settings, a US shipping zone, four
clearly-labelled `Sample:` products, CMS pages and homepage sections.

It deliberately seeds **no reviews, no ratings and no discounts** — fabricated social
proof and fake price comparisons are not acceptable store data.

To create an administrator, set these in `.env` and re-run the seeder:

```dotenv
SEED_ADMIN_EMAIL=admin@happykoop.test
SEED_ADMIN_PASSWORD=<a password you choose>
```

```bash
php artisan db:seed --class=AdminUserSeeder
```

The seeder prints nothing secret and hardcodes no credentials. Change the password after
first sign-in.

### Queue worker and scheduler

Webhook side effects and mail are queued (`QUEUE_CONNECTION=database`):

```bash
php artisan queue:work
php artisan schedule:work   # expires abandoned pending orders
```

Without a worker, payment webhooks are still **recorded** (so nothing is lost and
idempotency holds) but their side effects will not be applied until one runs.

---

## Commands

| Command | Purpose |
|---|---|
| `php artisan serve --host=127.0.0.1 --port=8000` | Run the API |
| `php artisan migrate` | Apply pending migrations (**never** `migrate:fresh`) |
| `php artisan migrate:status` | List migration state |
| `php artisan db:seed` | Idempotent dev seed |
| `php artisan test` | Test suite (in-memory SQLite) |
| `php artisan route:list --path=api` | Inspect the route table |
| `php artisan queue:work` | Process queued jobs |
| `vendor/bin/pint` | Code style |

---

## Architecture

```
app/
  Exceptions/ApiException.php     domain errors carrying an HTTP status + contract code
  Http/
    Controllers/Api/V1/           Public/, Account/, Admin/, Webhooks/
    Middleware/
      ForceJsonResponse.php       every /api/* request is JSON, whatever Accept says
      EnsureUserIsAdmin.php       applied on top of `abilities:admin`
    Requests/                     Form Requests; camelCase error keys
    Resources/                    response shapes, per the contract
  Models/                         explicit $fillable, never $guarded = []
  Policies/                       one per model; every admin action authorizes
  Services/
    Settings/                     SettingsRepository (cached), PaymentSettingsService
    Payments/                     StripeGateway, PayPalGateway, status machine
  Support/                        Money (integer minor units), CurrencyRegistry, formatter
bootstrap/app.php                 middleware aliases + the JSON error envelope
docs/api-contract.md              the shared contract
```

### Money

Money is stored and transported as **integer minor units**, never a float. Every monetary
value serializes as `{ amount, currency, formatted }`. Requests never carry prices — the
client sends ids and quantities, and the server computes every subtotal, discount,
shipping charge and total. Any price-like field in a request body is ignored.

### Authorization

Admin routes carry three independent layers: `auth:sanctum`, `abilities:admin`, and
`EnsureUserIsAdmin`, plus a Policy check inside each action. The `isAdmin` flag in a
response is a UI affordance only and is never treated as authorization.

### Secrets

Payment provider secrets are entered by an administrator and stored **encrypted at rest**
via Laravel's `encrypted` cast (AES-256 keyed by `APP_KEY`). They are never returned by
any endpoint: the API exposes only `{ configured, maskedSuffix, updatedAt }`. The generic
`SettingsRepository::get()` returns `null` for an encrypted key — `getSecret()` is the
only accessor that decrypts — so a secret cannot leak through the common read path.

An omitted or empty secret field on `PUT` means "leave unchanged", so the admin form can
be saved without re-entering secrets. An explicit `null` clears it.

---

## Environment variables

Names only. Never commit real values; `.env` is git-ignored.

### Required

| Name | Purpose |
|---|---|
| `APP_KEY` | Laravel app key. **Also the encryption key for stored payment secrets** — rotating it makes existing encrypted settings unreadable. |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | `APP_DEBUG=false` in production, always. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection. |
| `FRONTEND_URLS` | Comma-separated explicit CORS allowlist. Never `*`. |
| `FRONTEND_URL` | Canonical storefront URL, used for payment return URLs. |

### Optional

| Name | Purpose |
|---|---|
| `ALLOWED_PUBLIC_URL_HOSTS` | Host allowlist constraining the admin-editable `activeApiBaseUrl` / `canonicalFrontendUrl`. |
| `SANCTUM_CUSTOMER_TOKEN_MINUTES`, `SANCTUM_ADMIN_TOKEN_MINUTES` | Token lifetimes (default 30d / 12h). |
| `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` | Only for the cookie topology in contract §2.4. |
| `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD` | Dev-only admin seeding. Not in `.env.example`. |
| `MAIL_*` | Password resets and order mail. `MAIL_MAILER=log` locally. |
| `QUEUE_CONNECTION`, `CACHE_STORE` | `database` by default. |

**No Stripe or PayPal credential belongs in `.env`.** They are admin-managed and
encrypted in the database.

---

## Configuring Stripe and PayPal

Nothing is configured out of the box; both providers start disabled.

1. Sign in to the admin panel and open **Settings → Payments**.
2. **Stripe** — from the Stripe dashboard in *test* mode, paste the publishable key
   (`pk_test_…`) and secret key (`sk_test_…`). Key prefixes are validated against the
   selected mode, so a live key cannot be saved into test mode.
3. **PayPal** — from the PayPal developer dashboard, create a *sandbox* REST app and paste
   the client id and client secret.
4. Save. The response confirms `configured: true` and a masked suffix; the secret itself is
   never returned again.
5. Use **Test connection** to make a real server-side call (Stripe `/v1/account`, PayPal
   OAuth) and confirm the credentials work.

### Webhooks

Register these URLs in each provider's dashboard. The admin screen displays them read-only
so they can be copied exactly:

| Provider | URL | Then store |
|---|---|---|
| Stripe | `https://<your-api-host>/api/v1/webhooks/stripe` | the signing secret (`whsec_…`) |
| PayPal | `https://<your-api-host>/api/v1/webhooks/paypal` | the webhook id |

Stripe signatures are verified against the **raw request body**; PayPal events are verified
through PayPal's verification API. Every event id is persisted in `payment_events` behind a
unique index, so a replayed event is acknowledged and ignored exactly once. Webhooks are the
source of truth for asynchronous payment state — an order is never marked paid from a
browser redirect.

Webhook endpoints are CSRF-exempt, carry no auth middleware, and are excluded from the CORS
allowlist, because payment providers are servers rather than browsers.

### Going live

Switching a provider to live mode requires `confirmLiveMode: true` **and** an admin holding
the `payments.manage_live` permission; otherwise the API returns `423 LIVE_MODE_LOCKED`.

> Payments are **not** production-ready until real credentials are entered, webhook
> endpoints are registered and reachable over HTTPS, and you have completed successful test
> transactions end to end. The scaffolding being present is not the same as payments working.

---

## Deployment

**This application cannot be deployed to Vercel.** Vercel hosts the Next.js frontend;
a conventional Laravel app needs a PHP-capable host — a VPS, a container platform, or a
managed PHP host (Laravel Forge/Cloud, Fly.io, Railway, Render, cPanel).

Required for the API host:

- PHP 8.2+ with the extensions listed above, and Composer.
- MySQL/MariaDB reachable from the app.
- HTTPS (payment providers will not post webhooks to plain HTTP).
- Document root pointed at `public/`, never the project root.
- A writable `storage/` and `bootstrap/cache/`.
- A persistent queue worker and the scheduler.
- Object storage (S3-compatible) for media in production; the local `public` disk does not
  survive most container deployments.

Checklist:

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan migrate --force        # never migrate:fresh
php artisan storage:link
```

Set `APP_DEBUG=false`, `APP_ENV=production`, a strong unique `APP_KEY`, and
`FRONTEND_URLS` to the real storefront origin(s).

### What admin URL settings do and do not do

`activeApiBaseUrl` and `canonicalFrontendUrl` are **application settings**. Saving them
does not change DNS records, TLS certificates, the hosting provider, the Vercel project
domain or its environment variables, CORS allowlists, cookie domains, or payment-provider
webhook URLs. Each of those must be changed in its own system.

The bootstrap endpoint `GET /api/v1/public/runtime-config` must stay reachable at the
frontend's deployment-time `NEXT_PUBLIC_API_BOOTSTRAP_URL` even after `activeApiBaseUrl`
changes — that is what avoids a circular dependency. Moving the API to a new host is a
deployment change requiring DNS, TLS, CORS, env, cache and webhook updates, and testing.

### Rollback

Migrations are reversible (`php artisan migrate:rollback --step=1`), but **rolling back a
migration that drops a column destroys data**. Take a database backup before any migration
in production. Backups are a deployment requirement, not something this codebase provides.

---

## Testing

```bash
php artisan test
```

Runs against in-memory SQLite. Beyond the happy paths, the suite asserts the security
properties that would otherwise fail silently:

- `runtime-config` and the admin payment endpoint never contain a stored secret, under any
  key, at any depth — asserted against the raw response body.
- Public product endpoints never expose `cost_amount` or supplier fields.
- Admin endpoints return 401 unauthenticated and 403 for a customer token.
- An omitted secret field leaves the stored secret unchanged; live mode without explicit
  confirmation returns 423.
- A client-supplied price is ignored and the server total wins; a stale `cartHash` returns
  422; `shipping`/`tax` are `null` rather than a misleading `0`.
- Replaying an `Idempotency-Key` yields one order, not two; inventory is not committed
  before payment.
- A customer cannot read another customer's order (404, so existence is not disclosed);
  a guest order requires its access token.
- A webhook with a bad signature changes nothing; a replayed event is recorded once.
