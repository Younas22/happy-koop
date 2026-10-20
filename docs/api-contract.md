# HappyKoop — API Contract v1

**Status:** authoritative. Frontend and backend MUST both conform to this document.
**Base path:** `/api/v1`
**Local API origin:** `http://127.0.0.1:8000`
**Local frontend origin:** `http://localhost:3000`

Any change to this file must be made before the corresponding code change, and both
streams must be updated together.

---

## 1. Conventions

### 1.1 Casing
- JSON request and response bodies use **camelCase**.
- Database columns use snake_case. API Resources perform the mapping explicitly.
- Query string parameters use **snake_case** (`per_page`, `min_price`) to match Laravel idiom.

### 1.2 Success envelope
Every successful response is wrapped in `data`.

Single resource:
```json
{ "data": { "id": 12, "name": "Example" } }
```

Collection (non-paginated):
```json
{ "data": [ { "id": 1 }, { "id": 2 } ] }
```

Paginated collection:
```json
{
  "data": [ { "id": 1 } ],
  "meta": {
    "currentPage": 1,
    "perPage": 24,
    "total": 137,
    "lastPage": 6,
    "from": 1,
    "to": 24
  }
}
```

Action responses that return no resource:
```json
{ "data": { "ok": true } }
```

### 1.3 Error envelope
All errors (4xx/5xx) use this single shape:
```json
{
  "message": "Human readable summary.",
  "code": "MACHINE_CODE",
  "errors": { "fieldName": ["First problem.", "Second problem."] }
}
```
- `message` — always present. Safe to display.
- `code` — always present. Stable machine-readable string (see 1.4).
- `errors` — present **only** for `422` validation failures. Keys are camelCase field paths
  matching the request body (e.g. `shippingAddress.postalCode`, `items.0.quantity`).

Secrets, stack traces, SQL and provider raw errors are never included in `message`,
`errors`, or `code`.

### 1.4 Status codes and codes
| HTTP | `code` | Meaning |
|------|--------|---------|
| 200 | — | OK |
| 201 | — | Created |
| 204 | — | No content (no body) |
| 400 | `BAD_REQUEST` | Malformed request |
| 401 | `UNAUTHENTICATED` | Missing/invalid/expired credentials |
| 403 | `FORBIDDEN` | Authenticated but not permitted |
| 404 | `NOT_FOUND` | Resource does not exist or is not published |
| 409 | `CONFLICT` | State conflict (e.g. order already paid) |
| 410 | `GONE` | Expired checkout/payment attempt |
| 422 | `VALIDATION_FAILED` | Field validation failed; `errors` present |
| 422 | `CART_CHANGED` | Cart prices/availability changed since client state |
| 422 | `PAYMENT_NOT_CONFIGURED` | Provider not enabled/configured |
| 423 | `LIVE_MODE_LOCKED` | Live payment mode not explicitly authorized |
| 429 | `RATE_LIMITED` | Throttled. `Retry-After` header present |
| 500 | `SERVER_ERROR` | Unexpected failure |
| 503 | `SERVICE_UNAVAILABLE` | Provider/dependency unavailable |

Rate-limited responses always include the `Retry-After` header in seconds.

### 1.5 Money
Money is **never** a float. Every monetary value is this object:
```json
{ "amount": 2499, "currency": "USD", "formatted": "$24.99" }
```
- `amount` — **integer minor units** (cents). `2499` means 24.99 USD.
- `currency` — ISO 4217 uppercase.
- `formatted` — server-rendered display string using the store locale. Display only;
  never parse it.

Zero-decimal currencies (JPY, KRW) use `amount` as whole units; the backend derives the
exponent from a currency table and the frontend must not assume 2 decimals — always render
`formatted`, or divide by `10 ** minorUnitExponent` exposed in runtime config.

Requests never send money. The client sends product/variant ids and quantities; **the
server computes every price, subtotal, discount, shipping, tax and total**. Any monetary
value in a request body is ignored.

### 1.6 Dates
All timestamps are **UTC ISO-8601 with offset**: `"2026-03-14T09:21:00+00:00"`.
Date-only fields use `"2026-03-14"`. The store display timezone is in runtime config.

### 1.7 Nullability
A field that has no value is returned as `null` (for scalars) or `[]` (for lists) — it is
**never omitted** and never filled with a placeholder. The frontend hides sections whose
data is `null`/empty rather than rendering blank labels.

### 1.8 Pagination / filter / sort inputs
- `page` (int, >=1, default 1)
- `per_page` (int, 1..60, default 24)
- `sort` — validated against a per-endpoint allowlist. Invalid value => 422.
- Unknown query parameters are ignored, not echoed.

---

## 2. Authentication

### 2.1 Chosen mode (decision)
**Sanctum personal access tokens (Bearer), with the token held in an HttpOnly cookie
owned by the Next.js server.**

Rationale: in production the frontend (Vercel) and the API (PHP host) are on different
registrable domains. Sanctum's SPA cookie mode requires a shared parent domain, so it
cannot be relied upon for that topology. Browser-readable token storage
(`localStorage`) is disallowed by the task's security rules.

Resulting flow:
1. Browser posts credentials to a **Next.js route handler** (`POST /api/auth/login`),
   same-origin, CSRF-protected by the frontend.
2. The Next.js **server** calls `POST /api/v1/auth/login` on Laravel and receives a token.
3. Next.js stores the token in a cookie `hk_session`: `HttpOnly; Secure; SameSite=Lax;
   Path=/`. The browser can never read it.
4. Every authenticated Laravel call is made **from the Next.js server** with
   `Authorization: Bearer <token>`.

The browser therefore never holds a privileged token, and no cross-site cookie is needed.

`GET /api/auth/session` (Next.js, not Laravel) returns the sanitized viewer for client
components: `{ "data": { "user": { ... } | null } }`.

### 2.2 Token abilities
| Ability | Granted to |
|---------|-----------|
| `customer` | every authenticated customer |
| `admin` | users with an admin role |

Abilities are a defence-in-depth layer only. **Every admin endpoint independently verifies
the user's role/permission server-side via middleware and policies.** The `isAdmin` flag in
any response is for UI affordances only and is never trusted as authorization.

### 2.3 Token lifetime
- Customer tokens expire after **30 days** of inactivity (`sanctum.expiration`).
- Admin tokens expire after **12 hours**.
- `POST /api/v1/auth/logout` revokes the presented token only.
- `POST /api/v1/auth/logout-all` revokes every token for the user.

### 2.4 Alternative (documented, not default)
If the frontend and API are later served from one registrable domain
(`shop.happykoop.com` + `api.happykoop.com`), Sanctum cookie mode may be enabled by
setting `SANCTUM_STATEFUL_DOMAINS` and `SESSION_DOMAIN=.happykoop.com`, and calling
`GET /sanctum/csrf-cookie` first. This requires `SESSION_SECURE_COOKIE=true`,
`SESSION_SAME_SITE=lax`, and CORS `supports_credentials=true` with explicit origins.
Switching modes is a deployment decision, not a runtime setting.

### 2.5 Auth endpoints (Laravel)
| Method | Path | Auth | Throttle |
|--------|------|------|----------|
| POST | `/api/v1/auth/register` | public | 5/min/ip |
| POST | `/api/v1/auth/login` | public | 5/min/ip+email |
| POST | `/api/v1/auth/logout` | bearer | 30/min |
| POST | `/api/v1/auth/logout-all` | bearer | 10/min |
| GET | `/api/v1/auth/me` | bearer | 60/min |
| POST | `/api/v1/auth/forgot-password` | public | 3/min/ip |
| POST | `/api/v1/auth/reset-password` | public | 5/min/ip |
| POST | `/api/v1/auth/email/verify/{id}/{hash}` | public (signed) | 6/min |
| POST | `/api/v1/auth/email/resend` | bearer | 2/min |

`POST /api/v1/auth/login` request:
```json
{ "email": "a@b.com", "password": "secret123", "deviceName": "web" }
```
Response `200`:
```json
{
  "data": {
    "token": "12|AbCdEf...",
    "expiresAt": "2026-04-13T09:21:00+00:00",
    "abilities": ["customer"],
    "user": {
      "id": 7, "name": "Ada Lovelace", "email": "a@b.com",
      "emailVerifiedAt": null, "isAdmin": false, "createdAt": "2026-01-02T10:00:00+00:00"
    }
  }
}
```
Invalid credentials => `422` `VALIDATION_FAILED` with
`errors: { "email": ["These credentials do not match our records."] }` — the same response
for unknown email and wrong password, to avoid account enumeration.

`forgot-password` always returns `200 { "data": { "ok": true } }` regardless of whether the
email exists.

---

## 3. Public runtime config

### `GET /api/v1/public/runtime-config`
Public. No auth. Cacheable. This is the **bootstrap endpoint**; it must remain reachable at
the stable deployment-time `NEXT_PUBLIC_API_BOOTSTRAP_URL` even after an administrator
changes `apiBaseUrl`.

Response headers: `Cache-Control: public, max-age=60, stale-while-revalidate=300`.

```json
{
  "data": {
    "configVersion": "2026-03-14T09:21:00+00:00",
    "store": {
      "name": "HappyKoop",
      "tagline": "Everyday things, thoughtfully chosen.",
      "logoUrl": "http://127.0.0.1:8000/storage/branding/logo.svg",
      "logoDarkUrl": null,
      "faviconUrl": "http://127.0.0.1:8000/storage/branding/favicon.png",
      "email": "support@happykoop.test",
      "phone": null,
      "addressLines": ["Example street 1", "1000 Example City"]
    },
    "theme": {
      "primaryColor": "#2563EB",
      "accentColor": "#2563EB",
      "radius": "md"
    },
    "locale": {
      "currency": "USD",
      "currencySymbol": "$",
      "minorUnitExponent": 2,
      "locale": "en-US",
      "timezone": "UTC"
    },
    "urls": {
      "apiBaseUrl": "http://127.0.0.1:8000/api/v1",
      "canonicalFrontendUrl": "http://localhost:3000"
    },
    "seo": {
      "defaultTitle": "HappyKoop",
      "titleTemplate": "%s | HappyKoop",
      "defaultDescription": "...",
      "defaultOgImageUrl": null
    },
    "policies": {
      "shippingSummary": null,
      "returnsSummary": null
    },
    "features": {
      "reviews": true,
      "wishlist": false,
      "newsletter": false,
      "guestCheckout": true,
      "search": true,
      "publicStockCounts": false
    },
    "payments": {
      "stripe": { "enabled": false, "mode": "test", "publishableKey": null },
      "paypal": { "enabled": false, "mode": "sandbox", "clientId": null }
    },
    "social": { "instagram": null, "facebook": null, "tiktok": null, "x": null },
    "navigation": {
      "primary": [ { "label": "Shop", "href": "/shop" } ],
      "footer": [ { "heading": "Help", "links": [ { "label": "Shipping", "href": "/pages/shipping" } ] } ]
    }
  }
}
```

**Privacy guarantees for this endpoint** (backend must enforce, tests must assert):
- Only an explicit allowlist of settings keys is serialized.
- `stripe.secretKey`, `stripe.webhookSecret`, `paypal.clientSecret`,
  `paypal.webhookId`, mail credentials, DB credentials, app key and internal host details
  are **never** present under any key.
- `publishableKey` / `clientId` are returned **only** when that provider is `enabled`.

### Frontend bootstrap rules
1. Read `NEXT_PUBLIC_API_BOOTSTRAP_URL` (deployment-time, not secret).
2. `GET {bootstrap}/api/v1/public/runtime-config`.
3. **Validate** `data.urls.apiBaseUrl`: scheme must be `http` or `https`; in production
   `https` is required; the host must match the allowlist in
   `NEXT_PUBLIC_ALLOWED_API_HOSTS` (comma-separated). If validation fails, log and keep
   using the bootstrap origin. Never follow an unvalidated URL.
4. Use the validated `apiBaseUrl` for subsequent calls.
5. If the bootstrap fetch fails, fall back to `NEXT_PUBLIC_API_BASE_URL` (local dev
   convenience) and render the store with neutral defaults rather than crashing.
6. Cache for 60s server-side (`revalidate: 60`), tag `runtime-config`. Never cache
   indefinitely.

**Infrastructure limits (must be stated in the admin UI).** `apiBaseUrl` and
`canonicalFrontendUrl` are application settings. Saving them does **not** change DNS
records, TLS certificates, the hosting provider, the Vercel project domain, Vercel
environment variables, CORS allowlists, cookie domains, or payment-provider webhook URLs.
Each of those must be changed in its own system.

---

## 4. Catalog (public)

### `GET /api/v1/categories`
Query: `tree` (bool, default `false`), `active_only` (bool, default `true`).
Returns all active categories. With `tree=true`, children are nested under `children`.
```json
{ "data": [ {
  "id": 3, "name": "Kitchen", "slug": "kitchen", "parentId": null,
  "description": null, "imageUrl": null, "productCount": 42, "sortOrder": 1,
  "children": []
} ] }
```

### `GET /api/v1/categories/{slug}`
`404` if missing or inactive. Adds `seo` and `ancestors` (breadcrumb trail, root first).

### `GET /api/v1/products`
Public list. Only `status = published` products.

| Param | Type | Notes |
|-------|------|-------|
| `q` | string | max 100 chars, matches name/sku/short description |
| `category` | string | category slug; includes descendant categories |
| `min_price` | int | **minor units** |
| `max_price` | int | minor units |
| `in_stock` | bool | |
| `sort` | enum | `relevance` \| `newest` \| `price_asc` \| `price_desc` \| `name_asc` \| `name_desc`. Default `newest`, or `relevance` when `q` present |
| `page`, `per_page` | int | |

Returns paginated `ProductCard`:
```json
{
  "data": [ {
    "id": 101, "name": "Ceramic Pour-Over Dripper", "slug": "ceramic-pour-over-dripper",
    "shortDescription": "Single-cup dripper in matte stoneware.",
    "price": { "amount": 2900, "currency": "USD", "formatted": "$29.00" },
    "compareAtPrice": null,
    "primaryImage": { "id": 9, "url": "...", "alt": "Matte white dripper", "width": 1200, "height": 1200 },
    "availability": { "inStock": true, "state": "in_stock" },
    "rating": null,
    "categories": [ { "id": 3, "name": "Kitchen", "slug": "kitchen" } ],
    "hasVariants": true,
    "badges": []
  } ],
  "meta": { "currentPage": 1, "perPage": 24, "total": 137, "lastPage": 6, "from": 1, "to": 24 }
}
```

`rating` is `null` when the product has **zero approved reviews**. It is never 0, never a
seeded default, and never derived from supplier data. When present:
```json
"rating": { "average": 4.6, "count": 23 }
```

`availability.state` is one of `in_stock` | `low_stock` | `out_of_stock` | `preorder` |
`unavailable`. `low_stock` is emitted only when the store has configured a real threshold
and tracked inventory is at/below it — never as an urgency device.

`compareAtPrice` is returned **only when it is strictly greater than `price`** and was set
by an administrator. Otherwise `null`. The frontend must not compute or display a discount
percentage when it is `null`.

`badges` contains only administrator-set, factual labels (e.g. `{"label":"New","tone":"neutral"}`).
The backend never invents badges.

### `GET /api/v1/products/{slug}`
Full `ProductDetail`. `404` when not published.

```json
{ "data": {
  "id": 101,
  "name": "Ceramic Pour-Over Dripper",
  "slug": "ceramic-pour-over-dripper",
  "sku": "HK-DRIP-01",
  "brand": null,
  "status": "published",
  "shortDescription": "Single-cup dripper in matte stoneware.",
  "description": "<p>Sanitized HTML</p>",
  "valueProps": [ { "icon": "leaf", "title": "Lead-free glaze", "body": "..." } ],
  "price": { "amount": 2900, "currency": "USD", "formatted": "$29.00" },
  "compareAtPrice": null,
  "availability": { "inStock": true, "state": "in_stock", "quantity": null, "policy": "track" },
  "rating": null,
  "reviewCount": 0,
  "media": [
    { "id": 9, "type": "image", "url": "...", "thumbnailUrl": "...", "alt": "...",
      "width": 1200, "height": 1200, "sortOrder": 0 },
    { "id": 12, "type": "video", "url": "...", "thumbnailUrl": "...", "alt": null,
      "width": null, "height": null, "sortOrder": 5 }
  ],
  "options": [
    { "id": 1, "name": "Color", "slug": "color", "values": [
      { "id": 10, "label": "Matte White", "value": "matte-white", "swatchHex": "#F3F1EC" },
      { "id": 11, "label": "Charcoal", "value": "charcoal", "swatchHex": "#2F2F2F" }
    ] }
  ],
  "variants": [
    { "id": 55, "sku": "HK-DRIP-01-MW", "optionValueIds": [10],
      "price": { "amount": 2900, "currency": "USD", "formatted": "$29.00" },
      "compareAtPrice": null,
      "availability": { "inStock": true, "state": "in_stock", "quantity": 12, "policy": "track" },
      "mediaId": 9, "weightGrams": 420, "isDefault": true }
  ],
  "specificationGroups": [
    { "group": "Dimensions", "sortOrder": 0, "items": [
      { "label": "Height", "value": "95", "unit": "mm" },
      { "label": "Diameter", "value": "115", "unit": "mm" }
    ] },
    { "group": "Materials & care", "sortOrder": 1, "items": [
      { "label": "Material", "value": "Stoneware", "unit": null },
      { "label": "Dishwasher safe", "value": "Yes", "unit": null }
    ] }
  ],
  "whatsIncluded": [ "1 x ceramic dripper", "1 x stainless stand" ],
  "shippingInfo": { "summary": "...", "estimateText": null, "freeShippingThreshold": null },
  "returnsInfo": { "summary": "...", "windowDays": 30, "warrantyText": null },
  "faqs": [ { "id": 4, "question": "Does it fit a Hario filter?", "answer": "...", "sortOrder": 0 } ],
  "categories": [ { "id": 3, "name": "Kitchen", "slug": "kitchen" } ],
  "breadcrumbs": [ { "label": "Shop", "href": "/shop" }, { "label": "Kitchen", "href": "/category/kitchen" } ],
  "seo": { "title": null, "description": null, "canonicalPath": "/product/ceramic-pour-over-dripper", "ogImageUrl": "...", "noindex": false },
  "shippingDimensions": { "weightGrams": 420, "lengthMm": 140, "widthMm": 140, "heightMm": 110 },
  "publishedAt": "2026-02-01T08:00:00+00:00"
}}
```

**Variant selection contract.** A variant is identified by the **set** of
`optionValueIds` — one value per option, order-insensitive. The frontend resolves the
selected variant by matching that set. A combination that is not present in `variants` is
unavailable and the Add-to-Cart control must be disabled with an explicit message. The
frontend must not synthesize variants from the cartesian product of options.

`variants` is `[]` for a simple product; `hasVariants` is then `false` and the product-level
`price`/`availability` apply.

`availability.quantity` is `null` unless the store has enabled public stock counts
(`features.publicStockCounts`) **and** the product policy is `track`. `policy` is `track` |
`continue` | `deny`.

**Fields that must never appear on public product endpoints:** `costPrice`, supplier
identity, supplier revenue, supplier response time, supplier rating/ranking, wholesale or
MOQ tiers, supplier chat/LIVE handles, internal notes, margin. These live on admin
endpoints only (and supplier metrics are not modelled as store data at all).

### `GET /api/v1/products/{slug}/reviews`
Query: `page`, `per_page` (1..20, default 10), `sort` = `newest` | `highest` | `lowest` |
`helpful`, `rating` (1..5 filter).
Only `status = approved` reviews.
```json
{ "data": [ {
  "id": 31, "rating": 5, "title": "Brews beautifully",
  "body": "...", "authorName": "Ada L.", "isVerifiedPurchase": true,
  "createdAt": "2026-02-20T12:00:00+00:00", "helpfulCount": 2,
  "storeReply": null
} ], "meta": { },
  "summary": { "average": 4.6, "count": 23, "distribution": { "5": 15, "4": 6, "3": 1, "2": 0, "1": 1 } } }
```
`summary` is `null` when `count` is 0. `isVerifiedPurchase` is `true` **only** when the
review is linked to a paid order item for that product by that customer. Imported or
supplier reviews are not exposed here.

### `GET /api/v1/products/{slug}/related`
Query: `limit` (1..12, default 4). Returns `ProductCard[]` (non-paginated), chosen by
shared category then recency. Never pads with unrelated filler to reach `limit`.

### `POST /api/v1/reviews`
Auth: bearer (`customer`). Throttle 3/hour/user.
```json
{ "productId": 101, "rating": 5, "title": "Brews beautifully", "body": "..." }
```
- `rating` 1..5 integer, `title` max 120, `body` 10..4000.
- One review per customer per product (`409 CONFLICT` otherwise).
- Created with `status = pending`; it does not appear publicly until moderated.
- `isVerifiedPurchase` is computed server-side from paid orders. The client cannot set it.
- Response `201 { "data": { "id": 44, "status": "pending" } }`.

### `GET /api/v1/pages/{slug}`
Public CMS page. `404` when unpublished.
```json
{ "data": {
  "id": 2, "slug": "shipping", "title": "Shipping",
  "body": "<p>Sanitized HTML</p>", "excerpt": null,
  "seo": { "title": null, "description": null, "noindex": false },
  "updatedAt": "2026-03-01T09:00:00+00:00"
} }
```

### `GET /api/v1/home`
Homepage composition, admin-managed.
```json
{ "data": {
  "hero": { "headline": "...", "subheadline": null, "imageUrl": "...", "ctaLabel": "Shop now", "ctaHref": "/shop" },
  "sections": [
    { "type": "category_tiles", "heading": "Browse", "categories": [ ] },
    { "type": "product_grid", "heading": "Featured", "products": [ ] },
    { "type": "benefits", "heading": null, "items": [ { "icon": "truck", "title": "...", "body": "..." } ] },
    { "type": "rich_text", "heading": null, "html": "..." }
  ]
} }
```
`hero` may be `null`. Unknown section `type` values must be skipped by the frontend, not
rendered as errors.

---

## 5. Cart, shipping and checkout

The cart is **client-held, server-validated**. The browser keeps `{variantId|productId,
quantity}` pairs; the server is the only source of prices and availability.

### `POST /api/v1/cart/validate`
Public (works for guests). Throttle 60/min.
```json
{ "items": [ { "productId": 101, "variantId": 55, "quantity": 2 } ],
  "couponCode": null, "countryCode": "US", "postalCode": "97201" }
```
`quantity` 1..99 integer. Max 50 distinct lines.

Response `200`:
```json
{ "data": {
  "items": [ {
    "key": "101:55",
    "productId": 101, "variantId": 55,
    "name": "Ceramic Pour-Over Dripper",
    "variantLabel": "Matte White",
    "sku": "HK-DRIP-01-MW",
    "slug": "ceramic-pour-over-dripper",
    "imageUrl": "...",
    "quantity": 2,
    "maxQuantity": 12,
    "unitPrice": { "amount": 2900, "currency": "USD", "formatted": "$29.00" },
    "lineTotal": { "amount": 5800, "currency": "USD", "formatted": "$58.00" },
    "availability": { "inStock": true, "state": "in_stock" },
    "issues": []
  } ],
  "removedItems": [ { "productId": 90, "variantId": null, "reason": "unpublished",
                      "message": "This product is no longer available." } ],
  "adjustments": [ { "key": "101:55", "field": "quantity", "from": 5, "to": 2,
                     "reason": "insufficient_stock", "message": "Only 2 left." } ],
  "totals": {
    "subtotal": { "amount": 5800, "currency": "USD", "formatted": "$58.00" },
    "discount": { "amount": 0, "currency": "USD", "formatted": "$0.00" },
    "shipping": null,
    "tax": null,
    "total": { "amount": 5800, "currency": "USD", "formatted": "$58.00" }
  },
  "coupon": null,
  "currency": "USD",
  "cartHash": "sha256:9f2c"
} }
```
- `shipping` and `tax` are `null` until enough information exists to compute them
  (a selected shipping method, and a configured tax provider). `null` means
  **"not yet determined"** and the frontend displays "Calculated at checkout" — it never
  renders `null` as `$0.00`.
- `removedItems` / `adjustments` let the frontend reconcile and tell the customer exactly
  what changed. The frontend **must** replace its local cart with the returned `items`.
- `issues[]` entries use `{ "code": "...", "message": "..." }`.
- `cartHash` is a server-computed fingerprint of (variant ids, quantities, unit prices,
  currency). It is passed to `checkout/initialize`; a mismatch there returns `422
  CART_CHANGED`.

### `GET /api/v1/shipping/options`
Query: `country_code` (required, ISO-3166-1 alpha-2), `postal_code`, `subtotal_amount`
(int minor units — used only for free-shipping thresholds; the server re-derives the real
subtotal at checkout and ignores this for charging).
```json
{ "data": [ {
  "id": "standard", "label": "Standard", "description": "3-7 business days",
  "price": { "amount": 599, "currency": "USD", "formatted": "$5.99" },
  "estimateMinDays": 3, "estimateMaxDays": 7, "isDefault": true
} ] }
```
Returns `[]` when the store has configured no rates for that destination; the frontend then
blocks checkout for that country with a clear message rather than assuming free shipping.
`estimateMinDays`/`estimateMaxDays` are `null` unless an administrator configured them —
delivery dates are never invented.

### `POST /api/v1/checkout/initialize`
Public (guest) or bearer. Throttle 20/min. **Requires `Idempotency-Key` header** (UUIDv4).

```json
{
  "items": [ { "productId": 101, "variantId": 55, "quantity": 2 } ],
  "cartHash": "sha256:9f2c",
  "email": "a@b.com",
  "phone": null,
  "shippingAddress": {
    "firstName": "Ada", "lastName": "Lovelace", "company": null,
    "line1": "1 Example St", "line2": null, "city": "Portland",
    "region": "OR", "postalCode": "97201", "countryCode": "US", "phone": null
  },
  "billingAddress": null,
  "shippingMethodId": "standard",
  "couponCode": null,
  "paymentProvider": "stripe",
  "customerNote": null,
  "acceptsMarketing": false
}
```
- `billingAddress: null` means "same as shipping".
- `paymentProvider` is `stripe` or `paypal`, and must be currently enabled; otherwise
  `422 PAYMENT_NOT_CONFIGURED`.
- The request contains **no prices**. Any price-like field is ignored.

Response `201`:
```json
{ "data": {
  "order": {
    "id": 801, "orderNumber": "HK-2026-000801",
    "accessToken": "opaque-guest-token",
    "status": "pending", "paymentStatus": "unpaid",
    "currency": "USD",
    "totals": {
      "subtotal": { "amount": 5800, "currency": "USD", "formatted": "$58.00" },
      "discount": { "amount": 0, "currency": "USD", "formatted": "$0.00" },
      "shipping": { "amount": 599, "currency": "USD", "formatted": "$5.99" },
      "tax": { "amount": 0, "currency": "USD", "formatted": "$0.00" },
      "total": { "amount": 6399, "currency": "USD", "formatted": "$63.99" }
    },
    "items": [ ],
    "createdAt": "2026-03-14T09:21:00+00:00",
    "expiresAt": "2026-03-14T10:21:00+00:00"
  },
  "paymentAttempt": { "id": 900, "provider": "stripe", "status": "requires_payment" }
} }
```
Behaviour:
- Runs inside a DB transaction; re-validates every price, availability and the `cartHash`.
- Creates an order with `status=pending`, `payment_status=unpaid`, and **item snapshots**
  (name, sku, options, unit price) frozen at this moment.
- Does **not** decrement inventory. Stock is committed only when payment is confirmed.
- Replaying the same `Idempotency-Key` with the same body returns the **same** order
  (`200`, not a new order). The same key with a different body returns `409 CONFLICT`.
- `expiresAt` is 60 minutes out; expired pending orders are cleaned up by a scheduled job
  that only ever touches `pending`/`unpaid` orders.

### `GET /api/v1/orders/by-number/{orderNumber}`
Query: `token` (required for guests) — the opaque `accessToken` returned at initialize.
Bearer customers may instead fetch their own orders. Used by the checkout return pages to
read the **authoritative** status.
```json
{ "data": { "orderNumber": "HK-2026-000801", "status": "processing",
            "paymentStatus": "paid", "totals": { }, "items": [ ],
            "placedAt": "2026-03-14T09:25:00+00:00" } }
```
The success page must call this and must **never** infer payment from redirect query
parameters.

---

## 6. Payments

All amounts are taken from the stored order, never from the request.

### `POST /api/v1/payments/stripe/intent`
Bearer or guest-token. Throttle 20/min.
```json
{ "orderNumber": "HK-2026-000801", "accessToken": "..." }
```
Response:
```json
{ "data": { "provider": "stripe", "mode": "test",
            "clientSecret": "pi_3_secret_", "publishableKey": "pk_test_",
            "paymentIntentId": "pi_3", "amount": { } } }
```
`clientSecret` is a single-order, client-side confirmation credential — not an API secret.
The Stripe **secret key** and **webhook secret** never leave the server.

Alternative hosted flow: `POST /api/v1/payments/stripe/checkout-session` returns
`{ "data": { "url": "https://checkout.stripe.com/...", "sessionId": "cs_" } }`.

### `POST /api/v1/payments/paypal/order`
```json
{ "orderNumber": "HK-2026-000801", "accessToken": "..." }
```
Response: `{ "data": { "provider": "paypal", "mode": "sandbox", "paypalOrderId": "5O1", "amount": { } } }`

### `POST /api/v1/payments/paypal/capture`
```json
{ "orderNumber": "HK-2026-000801", "accessToken": "...", "paypalOrderId": "5O1" }
```
The server calls PayPal to capture, then **independently verifies** status, amount,
currency and the order reference before changing `payment_status`. Response returns the
order's authoritative status. A frontend "approved" callback alone never marks an order paid.

### Webhooks
| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/v1/webhooks/stripe` | Signature verified against the **raw body** with the configured webhook secret. CSRF-exempt. No auth middleware. |
| POST | `/api/v1/webhooks/paypal` | Verified via PayPal's verification API using the configured webhook id. |

Both:
- Return `200` quickly; side effects are queued.
- Persist every event id in `payment_events` with a unique index => **idempotent**; a
  replayed event is acknowledged and ignored.
- Validate amount, currency and order reference before any state transition.
- Are the **source of truth** for asynchronous payment state.
- Never log secrets or full payloads containing PII beyond what is needed.

### Payment status model
`paymentStatus` is one of `unpaid` | `authorized` | `paid` | `partially_refunded` |
`refunded` | `failed` | `cancelled`.
`status` (fulfilment) is one of `pending` | `processing` | `shipped` | `delivered` |
`cancelled` | `refunded`.
Allowed transitions are enforced server-side; an invalid transition returns `409 CONFLICT`.
Inventory is decremented exactly once, transactionally, on the first transition into
`paid`/`authorized`.

---

## 7. Customer endpoints
All require bearer with `customer` ability. All are scoped to the authenticated user —
every lookup filters by `user_id` (IDOR protection), returning `404` for others' records.

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/v1/account/profile` | |
| PATCH | `/api/v1/account/profile` | `name`, `phone`, `acceptsMarketing` |
| PUT | `/api/v1/account/password` | `currentPassword`, `password`, `passwordConfirmation` |
| GET | `/api/v1/account/orders` | paginated summaries |
| GET | `/api/v1/account/orders/{orderNumber}` | full detail |
| GET | `/api/v1/account/addresses` | |
| POST | `/api/v1/account/addresses` | |
| PATCH | `/api/v1/account/addresses/{id}` | |
| DELETE | `/api/v1/account/addresses/{id}` | `204` |
| POST | `/api/v1/account/addresses/{id}/default` | `type` = `shipping` \| `billing` |
| GET | `/api/v1/account/reviews` | own reviews incl. pending |

---

## 8. Admin endpoints

**Prefix:** `/api/v1/admin`. **Every** route is behind `auth:sanctum` +
`abilities:admin` + an `EnsureUserIsAdmin` middleware, **and** each controller action
authorizes via a Policy. Hiding a menu item in the UI is not access control.

| Method | Path | Notes |
|--------|------|-------|
| GET | `/admin/dashboard` | real metrics only; no projections |
| GET/POST | `/admin/products` | list (incl. drafts) / create |
| GET/PATCH/DELETE | `/admin/products/{id}` | soft delete |
| POST | `/admin/products/{id}/media` | attach uploaded media |
| PATCH | `/admin/products/{id}/media/reorder` | |
| PUT | `/admin/products/{id}/variants` | replace variant set atomically |
| PUT | `/admin/products/{id}/specifications` | replace spec set |
| PUT | `/admin/products/{id}/faqs` | |
| PUT | `/admin/products/{id}/related` | |
| GET/POST | `/admin/categories` | |
| PATCH/DELETE | `/admin/categories/{id}` | rejects cycles in `parentId` |
| GET | `/admin/orders` | filters: status, paymentStatus, q, date range |
| GET | `/admin/orders/{id}` | |
| PATCH | `/admin/orders/{id}/status` | validated transitions |
| PATCH | `/admin/orders/{id}/fulfilment` | carrier, trackingNumber, trackingUrl |
| POST | `/admin/orders/{id}/refund` | provider refund; amount <= refundable |
| GET | `/admin/customers` | search; no password hashes, no full payment data |
| GET | `/admin/customers/{id}` | |
| GET | `/admin/reviews` | filter by status |
| PATCH | `/admin/reviews/{id}` | `status` = approved \| rejected, `storeReply` |
| GET/POST | `/admin/pages` | |
| PATCH/DELETE | `/admin/pages/{id}` | |
| GET/PUT | `/admin/home-sections` | |
| GET/POST | `/admin/media` | upload |
| DELETE | `/admin/media/{id}` | |
| GET/PUT | `/admin/settings/store` | |
| GET/PUT | `/admin/settings/payments` | see 8.2 |
| POST | `/admin/settings/payments/test` | server-side connection check |
| GET | `/admin/audit-logs` | paginated, read-only |
| GET/POST | `/admin/users` | role assignment |
| PATCH/DELETE | `/admin/users/{id}` | cannot remove own admin role or self-delete |

### 8.1 `PUT /admin/settings/store`
Validated fields include `storeName`, `tagline`, `logoMediaId`, `logoDarkMediaId`,
`faviconMediaId`, `primaryColor` (`/^#[0-9a-fA-F]{6}$/`), `accentColor`, `contactEmail`,
`contactPhone`, `addressLines`, `currency` (ISO 4217, from a supported list), `locale`,
`timezone` (valid tz identifier), `canonicalFrontendUrl`, `activeApiBaseUrl`, SEO
defaults, policy summaries, social links and feature toggles.

URL validation: must be absolute, scheme `http` or `https` only, no credentials in the
URL, normalized trailing slash, host must be a valid hostname or IP, and must appear in the
server-side `ALLOWED_PUBLIC_URL_HOSTS` allowlist when that is configured. A rejected URL
returns `422` and the previous value is retained.

The response **must** include the advisory the admin UI displays verbatim:
```json
"infrastructureNotice": "These are application settings. Saving them does not change DNS, TLS certificates, hosting, the Vercel project domain or its environment variables, CORS allowlists, cookie domains, or payment-provider webhook URLs. Each must be updated in its own system."
```
Saving invalidates the runtime-config cache and bumps `configVersion`.

### 8.2 Payment settings — secret handling
`GET /admin/settings/payments` returns **status, never secrets**:
```json
{ "data": {
  "stripe": {
    "enabled": true, "mode": "test",
    "publishableKey": "pk_test_51Hx",
    "secretKey": { "configured": true, "maskedSuffix": "4242", "updatedAt": "2026-03-01T09:00:00+00:00" },
    "webhookSecret": { "configured": false, "maskedSuffix": null, "updatedAt": null },
    "webhookUrl": "https://api.happykoop.com/api/v1/webhooks/stripe",
    "lastCheck": { "ok": true, "checkedAt": "2026-03-01T09:05:00+00:00", "message": "Connected to account acct_1Hx" }
  },
  "paypal": {
    "enabled": false, "mode": "sandbox", "clientId": null,
    "clientSecret": { "configured": false, "maskedSuffix": null, "updatedAt": null },
    "webhookId": { "configured": false, "maskedSuffix": null, "updatedAt": null },
    "webhookUrl": "https://api.happykoop.com/api/v1/webhooks/paypal",
    "lastCheck": null
  }
} }
```
Rules the backend enforces and tests assert:
- A decrypted secret is **never** returned by any endpoint, in any field, in any error.
- Secrets are stored with Laravel `encrypted` casts (AES-256 via `APP_KEY`).
- `PUT` treats an **omitted** or empty-string secret field as "leave unchanged" — so the
  admin form can submit without re-entering secrets. An explicit `null` clears it.
- Enabling `mode: "live"` requires `confirmLiveMode: true` in the request body and an
  admin with the `payments.manage_live` permission; otherwise `423 LIVE_MODE_LOCKED`.
- Key format is validated (`sk_test_*`/`sk_live_*`, `pk_test_*`/`pk_live_*`) and must match
  the selected mode. Mismatch => `422`.
- Every write records an audit-log entry with actor, ip, changed key **names** only.
- Validation errors never echo the submitted secret value.

`POST /admin/settings/payments/test` performs a live server-side call (Stripe
`/v1/account`, PayPal OAuth token) and returns
`{ "data": { "ok": true, "message": "...", "checkedAt": "..." } }`. The message is sanitized.

---

## 9. Media upload

### `POST /api/v1/admin/media`
`multipart/form-data`: `file`, optional `alt`, `folder`.
- Allowed MIME: `image/jpeg`, `image/png`, `image/webp`, `image/avif`, `video/mp4`.
  SVG is rejected by default (script-carrying vector) unless explicitly enabled and
  sanitized.
- Max 8 MB images, 64 MB video. Max dimensions 8000x8000.
- The real MIME is detected server-side; the client-supplied `Content-Type` and filename
  extension are not trusted.
- Stored with a generated random filename on the `public` disk (dev) or object storage
  (prod). The original filename is kept only as metadata.
- Files are served from a static media path, never executed. `.php`, `.phtml`, `.htaccess`
  and double extensions are rejected.
```json
{ "data": { "id": 9, "url": "...", "thumbnailUrl": "...", "type": "image",
            "mimeType": "image/webp", "width": 1200, "height": 1200,
            "sizeBytes": 184320, "alt": "...", "createdAt": "..." } }
```

---

## 10. CORS, CSRF and local origins

- `config/cors.php` applies to `api/*` and `sanctum/csrf-cookie`.
- `allowed_origins` is an **explicit list** from `FRONTEND_URLS` (comma-separated).
  Wildcard `*` is never combined with credentials.
- Local: `http://localhost:3000`, `http://127.0.0.1:3000`.
- `supports_credentials` is `true` only in the Sanctum-cookie topology (section 2.4); in
  the default bearer topology it is `false`, since authenticated calls originate from the
  Next.js server, not the browser.
- Webhook routes are excluded from CSRF and from the CORS allowlist (providers are not
  browsers).

---

## 11. Frontend environment variables (names only)

| Name | Public | Purpose |
|------|--------|---------|
| `NEXT_PUBLIC_API_BOOTSTRAP_URL` | yes | Stable deployment-time API origin for runtime-config |
| `NEXT_PUBLIC_API_BASE_URL` | yes | Documented local/dev fallback |
| `NEXT_PUBLIC_ALLOWED_API_HOSTS` | yes | Comma-separated host allowlist validating `apiBaseUrl` |
| `NEXT_PUBLIC_SITE_URL` | yes | Canonical frontend URL for metadata |
| `SESSION_COOKIE_SECRET` | **no** | Signs the HttpOnly session cookie |
| `INTERNAL_API_BASE_URL` | **no** | Optional server-only API origin |

No Stripe secret, PayPal secret, webhook secret, database credential or `APP_KEY` may ever
appear in a `NEXT_PUBLIC_*` variable or in frontend source.

---

## 12. Decisions log

| # | Topic | Decision |
|---|-------|----------|
| 1 | Auth mode | Bearer token held in Next.js-owned HttpOnly cookie (section 2.1) |
| 2 | Money | Integer minor units + `formatted` (section 1.5) |
| 3 | Casing | camelCase JSON (section 1.1) |
| 4 | Cart | Client-held, server-validated, `cartHash` guard (section 5) |
| 5 | Tax | No tax engine in v1. `tax` is `null` until a provider is configured and is never presented as a legal tax calculation. |
| 6 | Coupons | Schema + validation implemented; promotional stacking rules out of scope for v1 |
| 7 | Inventory | Decrement on confirmed payment only, transactional, idempotent |
| 8 | Guest checkout | Supported, guarded by an opaque per-order `accessToken` |
