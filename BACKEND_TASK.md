# HappyKoop — Backend Implementation Task
## Laravel REST API + MySQL + admin settings + payments

### 1. Project goal
Build the secure backend for **HappyKoop**, a dynamic dropshipping ecommerce website. The frontend is Next.js; Laravel is the only layer allowed to access MySQL. Use the existing database named **`happykoop`**. Do not drop, recreate, or wipe this database. Inspect the existing schema first and create safe, reversible migrations.

### 2. Required stack and conventions
- Laravel (use the version already present in the repository; if new, choose a currently supported stable version compatible with the environment).
- MySQL database `happykoop`.
- REST API under `/api/v1`.
- Laravel Sanctum for first-party authentication, with secure cookie/CSRF/CORS setup where applicable.
- Form Requests for validation, Policies/Gates for authorization, API Resources for response shapes, service classes for payment/provider logic, and database transactions for order creation.
- Queue jobs for emails, webhook side effects, and slow tasks where queue infrastructure is configured.
- Use decimal-safe integer minor units or a well-defined decimal strategy for money; never use floating-point math for payment totals.
- Use UTC for stored timestamps and format dates consistently in API responses.
- Keep secrets in environment/secure server configuration or encrypted storage; never return secrets from API responses.
- Do not assume the frontend and backend share the same origin in production.

### 3. Database safety
- Database name: `happykoop` (already created in phpMyAdmin).
- Inspect current tables and migrations before changing schema.
- Never run destructive commands such as dropping the database, `migrate:fresh`, or truncating user/order tables.
- Create additive migrations where possible, include indexes/foreign keys, and provide rollback steps.
- Seed only safe demo data in local/development mode and never overwrite existing production records.
- Document the required `.env` settings without including real credentials.

### 4. Core data model
Create or adapt migrations/models for these areas. Reuse existing tables when equivalent ones already exist; avoid duplicate concepts.

#### Store / runtime settings
- Store settings: store name, logo URL/path, optional dark logo, favicon, primary/accent colors, contact information, default currency/locale/timezone, canonical frontend URL, active API base URL, SEO defaults, public shipping/returns text, social links, feature toggles.
- Public settings must be explicitly allowlisted. Never expose payment secrets, internal host details, credentials, or private settings through public config.
- Settings table should support key/value or typed columns with validation and auditability.
- API URL and canonical frontend URL are configuration metadata only. They cannot change DNS, TLS, hosting, Vercel domain configuration, or the actual server address. Document this in the admin-facing API contract.
- Runtime-config bootstrap endpoint must remain reachable at a stable configured bootstrap URL even when `active_api_base_url` changes. Avoid a circular dependency: frontend uses a deployment-time bootstrap URL to fetch the current public config, then uses the returned API base URL. Validate allowed schemes/origins and restrict changes to authorized administrators.
- Consider config caching with explicit cache invalidation when settings are saved.

#### Catalog
- Categories: name, slug, parent_id, description, image, active flag, SEO fields, sort order.
- Products: name, slug, SKU, short description, full description, status, brand (optional), category, price, compare-at price, cost (private), currency, inventory/availability policy, weight and shipping dimensions where available, SEO fields, timestamps.
- Product images/media: product_id, path/URL, alt text, sort order, type (image/video) and thumbnail where applicable.
- Product variants: product_id, SKU, option values, price override if needed, stock/availability, weight/dimensions if relevant.
- Product attributes/specifications: normalized key/label/value/unit/group/order so technical details can be grouped in the UI.
- Product option definitions and values where needed.
- Product FAQs and related-product relations.
- Reviews: customer, product, order/item relation if verified purchase, rating, title/body, moderation status, timestamps.
- Avoid fabricating ratings, sales counts, certifications, inventory, shipping dates, or customer reviews.

#### Customers and access
- Customer users and admin users/roles/permissions; use the existing auth model if present.
- Addresses with ownership and validation.
- Password reset and email verification where appropriate.
- Backend-enforced role/permission checks for every admin endpoint.
- Rate-limit login, password reset, review submission, coupon checks, and sensitive settings updates.
- Add audit records for sensitive admin actions/configuration changes if feasible.

#### Cart and checkout
- Server-side cart or validated cart payload strategy; never trust browser-submitted prices.
- Cart items reference product/variant IDs and quantities.
- Shipping methods/rates and tax rules should be configurable or implemented as explicit providers; do not invent tax/legal behavior.
- Coupons/discounts only if implemented with validation, expiry, usage limits, and transactional application.
- Orders: unique public order number, customer/guest details as supported, currency, item snapshots, subtotal, discount, shipping, tax, total, payment provider, payment status, order status, shipping/billing address snapshots, tracking fields, timestamps.
- Order items preserve SKU, product/variant name, selected options, quantity, unit price and totals at time of purchase.
- Inventory reservation/decrement must be transaction-safe and idempotent.
- Use idempotency keys or equivalent protection against duplicate checkout/order creation.
- Never mark an order paid based only on a browser redirect.

### 5. API contract (coordinate with frontend)
Use consistent JSON responses and Laravel API Resources. Document each endpoint, auth requirement, request schema, response schema, validation errors, and pagination format.

Suggested public endpoints:
- `GET /api/v1/public/runtime-config` — only allowlisted public store settings, including `storeName`, `logoUrl`, `faviconUrl`, `theme`, `currency`, `locale`, `canonicalFrontendUrl`, `apiBaseUrl`, public contact/policy summaries, feature flags and public payment-provider identifiers only when needed.
- `GET /api/v1/categories`
- `GET /api/v1/categories/{slug}`
- `GET /api/v1/products` — search, category, price filters, sort, pagination.
- `GET /api/v1/products/{slug}`
- `GET /api/v1/products/{slug}/reviews`
- `GET /api/v1/products/{slug}/related`
- `GET /api/v1/pages/{slug}`
- `GET /api/v1/shipping/options` — if rate logic is implemented.
- `POST /api/v1/cart/validate` — validates product IDs, variant IDs, quantity and current prices.
- `POST /api/v1/checkout/initialize` — validate cart, addresses, totals, currency and create a pending order/payment attempt.
- `POST /api/v1/payments/stripe/intent` or equivalent server-created Stripe Checkout Session endpoint.
- `POST /api/v1/payments/paypal/order` and `POST /api/v1/payments/paypal/capture` or equivalent server-side PayPal order/capture flow.
- `POST /api/v1/reviews` — authenticated or appropriately moderated submission.
- Customer endpoints for profile, addresses, cart, order history and order details.
- Auth endpoints according to Sanctum setup.

Suggested admin endpoints, all protected by server-side role/permission middleware:
- Dashboard metrics and recent orders.
- CRUD for products, variants, media, categories, attributes, FAQs and pages.
- Order list/details/status/fulfilment and supported refund actions.
- Customer lookup and review moderation.
- Store settings read/update.
- Payment settings read/update/test-connection.
- Media upload/delete.
- Users/roles management if in scope.
- Audit log read if implemented.

API details:
- Use predictable HTTP status codes and consistent validation errors.
- Paginate lists and validate filters/sort fields against allowlists.
- Return only fields required by each audience; keep cost price, internal notes, provider secrets and private customer data out of public endpoints.
- Add API feature tests for public catalog, validation, permissions, settings privacy, order creation and payment webhook behavior.
- Publish an OpenAPI/endpoint contract or a clear `docs/api-contract.md` file for frontend integration.

### 6. Authentication and authorization
- Use Laravel Sanctum appropriate to deployment topology.
- Configure CORS for explicit local and production frontend origins; do not use wildcard origins with credentialed cookies.
- Configure CSRF, SameSite, Secure and cookie domains correctly for the actual production domain arrangement.
- Admin endpoints must verify role/permission server-side.
- Rate-limit authentication and sensitive operations.
- Never log passwords, bearer tokens, payment secrets, or full card details.

### 7. Stripe integration
- Stripe secret key and webhook signing secret must remain server-side.
- Store admin-entered secrets encrypted at rest (Laravel encrypted casts or a dedicated encrypted settings service) and never return decrypted secrets from any API.
- Frontend receives only the publishable key where needed.
- Support test/live mode with clear validation and status indicators.
- Create PaymentIntent or Checkout Session server-side from validated order totals.
- Verify Stripe webhook signatures using the raw request body and configured webhook secret.
- Treat webhooks as the source of truth for asynchronous payment updates; implement idempotent event processing.
- Validate amount, currency, order reference and payment state before marking an order paid.
- Handle success, failure, cancellation, retries and refunds where enabled.
- Do not store card PAN/CVV or process raw card data directly.

### 8. PayPal integration
- Client ID may be public when required by the SDK; client secret stays server-side and encrypted at rest.
- Support sandbox/live mode.
- Create PayPal orders on the server using the validated order total and currency.
- Capture/verify payment on the server and validate status, amount, currency and reference.
- Verify webhook authenticity according to PayPal's current API requirements if webhooks are used.
- Process events idempotently and do not trust frontend callbacks alone.
- Never expose the client secret in admin GET responses or public runtime config.

### 9. Payment settings UI contract
Admin should be able to:
- Enable/disable Stripe and PayPal.
- Select test/sandbox versus live mode.
- Enter/update provider keys through secure HTTPS forms.
- Run a server-side configuration/connection check where supported.
- See configured/not-configured status, last validation result, and masked key suffix only.
- Save secrets encrypted; blank secret fields on subsequent reads.
- Avoid logging or echoing secrets in validation errors, API responses, HTML, or logs.
- Validate that live mode is explicitly enabled by an authorized admin.

### 10. Media, logo and dynamic content
- Provide secure media upload with file type, size, MIME, image dimension and authorization checks.
- Store paths/URLs in the database and expose safe public URLs.
- Support dynamic store logo, favicon, product images, category images, banners, homepage sections, static pages, FAQs and policies.
- Validate uploads; do not allow executable files to be uploaded/served as executable.
- Configure local public storage for development and a production-compatible object storage/disk when deploying.
- Frontend must not rely on a hardcoded logo or hardcoded content.

### 11. Security and operational requirements
- Validate all input; protect against mass assignment, SQL injection, XSS, CSRF, IDOR, privilege escalation and insecure file uploads.
- Use policies and least privilege.
- Set secure production headers and HTTPS requirements at the host/proxy.
- Add request throttling and safe exception handling.
- Keep `.env` out of version control; provide `.env.example` placeholders only.
- Do not expose `APP_DEBUG=true` in production.
- Keep logs useful but redact sensitive values.
- Use database transactions for checkout/order/payment state transitions.
- Add scheduled cleanup for expired pending orders only if safe and documented.
- Backups, mail provider, queue worker, and production storage must be documented as deployment requirements rather than silently assumed.

### 12. Local setup
- Use the existing MySQL database `happykoop`; configure DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD in Laravel `.env`.
- Local API default: `http://127.0.0.1:8000`.
- Frontend default: `http://localhost:3000`.
- Configure CORS/Sanctum for the local frontend origin.
- Provide safe migrations and optional development-only seeders.
- Include commands for install, migrate (not migrate:fresh), seed demo data, storage link, queue worker, tests and API serve.
- Never put MySQL credentials in Next.js.

### 13. Deployment constraints
- Next.js frontend can be deployed to Vercel.
- A conventional Laravel application requires a PHP-capable host/container/VPS/platform; do not assume it can be deployed as a standard Laravel app directly to Vercel.
- Production API must be reachable over HTTPS and configured for the production frontend origin, mail, storage, queue, database and webhooks.
- Admin-managed `canonicalFrontendUrl` and `activeApiBaseUrl` are application settings; they do not modify DNS, TLS certificates, hosting, Vercel environment variables, or infrastructure routing.
- Keep the bootstrap endpoint stable. Changing the API endpoint may require deployment configuration, DNS/CORS/cookie updates, provider webhook updates, cache refresh and testing.
- Provide a deployment checklist, rollback guidance and required environment variables.

### 14. Deliverables
- Laravel API source and safe additive migrations.
- Models, policies, Form Requests, Resources, services and controllers.
- Secure Stripe and PayPal integration scaffolding with explicit setup instructions; do not claim live payments work until keys, webhooks and provider settings are configured and tested.
- `.env.example` with placeholders only.
- `docs/api-contract.md` (or OpenAPI equivalent) shared with frontend.
- Tests for important catalog, admin, order, settings and payment behavior.
- Local setup and production deployment documentation.
- A list of any incomplete integrations and required credentials/configuration.
