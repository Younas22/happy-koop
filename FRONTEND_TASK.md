# HappyKoop — Frontend Implementation Task
## Next.js storefront + customer account + admin UI

### 1. Project goal
Build a production-minded, fully responsive ecommerce storefront for **HappyKoop**, a dropshipping store. The product-detail experience must support rich product information without overwhelming customers. The UI must be modern, easy to use, product-first, and mobile-first.

### 2. Required stack
- Next.js App Router, TypeScript, React.
- Tailwind CSS.
- Use a consistent accessible component system (shadcn/ui is acceptable) and Lucide icons.
- Use server components by default; client components only where interaction requires them.
- Use React Hook Form + Zod or equivalent validation for forms.
- Use the official Stripe and PayPal browser SDKs only where required by the payment flow; payment intent/order creation and verification must happen through Laravel.
- Do not build a fake backend or hardcode production product data. Use typed API clients and backend endpoints.
- Keep secrets out of frontend code. Never place Stripe secret keys, PayPal secrets, database credentials, or Laravel app keys in `NEXT_PUBLIC_*` variables.

### 3. Visual direction
- Brand: HappyKoop.
- Use a clean, modern, premium ecommerce style with a subtle **material-inspired feel**: tactile product photography, restrained surfaces, clear hierarchy, comfortable spacing, soft borders, and purposeful elevation.
- No gradients.
- Use a light neutral base (warm white / very light gray), dark readable text, and a restrained primary accent. Use `#2563EB` as the initial primary blue unless the admin-managed brand settings provide another valid theme color.
- Avoid clutter, excessive badges, tiny buttons, noisy animations, and fake urgency.
- Product images should be prominent and crisp. Prefer real product imagery supplied by the backend.
- Fully responsive at mobile, tablet, laptop, and wide desktop sizes.
- Meet accessibility basics: keyboard navigation, visible focus, labels, sufficient contrast, semantic headings, and accessible dialogs/accordions.
- Use loading skeletons, empty states, error states, and helpful form feedback.

### 4. Main routes / screens
Create a clear route map and implement at least:
- `/` — home page with header, category navigation, search, hero/featured collection, category tiles, featured products, benefits/trust section, newsletter (if enabled), and footer.
- `/shop` — product listing with search, category, sort, price filters, pagination or load-more, and responsive product cards.
- `/category/[slug]` — category listing and SEO metadata.
- `/product/[slug]` — complete product detail page.
- `/search?q=...` — search results.
- `/cart` — editable cart, quantities, remove item, subtotal and shipping/tax estimates when available.
- `/checkout` — address, shipping choice, order summary, and payment selection.
- `/checkout/success` and `/checkout/cancel` — payment return states; final order status must be confirmed with Laravel, never inferred only from query parameters.
- `/account` — customer dashboard.
- `/account/orders`, `/account/orders/[id]`, `/account/profile`, `/account/addresses`.
- `/login`, `/register`, `/forgot-password`, `/reset-password`.
- `/pages/[slug]` — CMS pages such as About, Contact, Privacy Policy, Terms, Shipping, Returns.
- A protected admin interface under `/admin` for authorized administrators only. Admin data operations must use Laravel APIs and backend authorization; hiding a menu is not security.

### 5. Product detail page — required content and UX
Use the previously agreed **hybrid layout**: essential purchase information visible immediately, all additional verified details accessible in clearly named sections/accordions.

Desktop:
- Breadcrumbs.
- Product gallery on the left (main image, thumbnails, zoom/lightbox, video when available).
- Purchase panel on the right: title, current price, compare-at price only when valid, verified rating/review count, stock/availability, variants, quantity, Add to Cart, Buy Now if enabled, delivery estimate, and concise returns/payment reassurance.
- Keep purchase CTA visually prominent and usable.

Mobile:
- Stack gallery and purchase panel logically.
- Provide a compact sticky Add to Cart bar only when it does not cover important controls or keyboard focus.
- Use large tap targets and avoid horizontal overflow.

Below the purchase area:
1. Short value proposition / benefits.
2. Product-in-use images or video and usage examples.
3. What's included in the box.
4. Specifications accordion: material, dimensions, weight, color, pack contents, care instructions, origin and other product-specific attributes.
5. Shipping and delivery information.
6. Returns/refunds and warranty policy, only as configured by the store.
7. Reviews and ratings; label verified purchases accurately.
8. FAQs.
9. Related/recommended products.
10. SEO description and structured data using only accurate fields.

Rules:
- Show as much useful product detail as possible without dumping every field into the first viewport.
- Use human-readable labels and group technical fields logically.
- Do not invent dimensions, certifications, stock, review counts, shipping estimates, discount prices, guarantees, or product claims.
- Supplier-only data (supplier revenue, response time, supplier rank, wholesale tiers, supplier chat/LIVE controls) must not appear on the customer storefront by default.
- Supplier reviews must not be represented as HappyKoop customer reviews.
- Product variants and pack sizes must be sourced from the API; do not invent options.
- Wholesale/MOQ information belongs in a separate wholesale inquiry flow if the business chooses to support it.
- Handle missing data gracefully: hide empty sections instead of showing blank labels.

### 6. Dynamic branding and store settings
The frontend must load public runtime/store settings from Laravel, including:
- Store name and logo (light/dark variants if configured).
- Favicon.
- Primary/accent colors and basic theme settings.
- Currency, locale, store contact information.
- Public shipping/returns summaries.
- Public frontend canonical URL and SEO defaults.
- Feature toggles (e.g., reviews, wishlist, newsletter) if exposed by backend.

Do not hardcode the logo in multiple places. Build a shared `BrandLogo` component that uses the active backend configuration and has a safe text-logo fallback.

#### API base URL / runtime config strategy
Implement a runtime config bootstrap:
- `NEXT_PUBLIC_API_BOOTSTRAP_URL` is a deployment-time setting pointing to a stable Laravel API endpoint. It is only the initial bootstrap address, not a secret.
- Fetch public runtime config from the bootstrap endpoint, e.g. `GET /api/v1/public/runtime-config`.
- Use the returned `apiBaseUrl` for subsequent API requests, after validating it is an allowed HTTP(S) origin.
- Keep a documented fallback to `NEXT_PUBLIC_API_BASE_URL` for local development if runtime config cannot be fetched.
- Never let arbitrary API URLs from untrusted browser input be used.
- Admin can edit the active API base URL and canonical frontend URL in Store Settings. Explain in the UI that changing the live API endpoint requires the new API to expose the same runtime-config contract and may require cache/session refresh. Keep the bootstrap URL stable or update it through deployment configuration when migrating environments.
- Do not claim an admin setting can change DNS, the Vercel project domain, TLS, or hosting configuration automatically.
- Avoid caching runtime config indefinitely; use a short, documented cache and an explicit refresh mechanism.

### 7. Authentication, authorization, and API client
- Integrate with Laravel Sanctum using the backend team's agreed auth mode. Prefer secure HttpOnly cookies for first-party web auth where deployment topology supports it; configure CORS, CSRF, cookie domain, and HTTPS correctly.
- If using bearer tokens, keep the chosen token handling secure and document the trade-offs; do not store long-lived privileged tokens in localStorage.
- Create a typed API client with centralized base URL, error normalization, timeout/abort handling, and auth/session handling.
- Never trust role or permissions supplied by the browser; backend authorization is authoritative.
- Admin routes should redirect unauthorized users and display useful forbidden/error states.

### 8. Payments
- Checkout should offer Stripe and PayPal only when enabled by the backend settings and supported for the current environment/currency.
- Backend creates payment/order sessions and verifies webhooks/payment status. Frontend only renders provider-approved payment UI and submits the returned confirmation to Laravel.
- Never read or display secret keys in the frontend. Public Stripe publishable key / PayPal client ID may be exposed only through an explicit public payment config endpoint if needed.
- Include pending, success, cancelled, failed, and retry states.
- Prevent double submissions and repeated order creation.

### 9. Admin UI requirements
Implement responsive admin navigation and screens for:
- Dashboard: orders, sales, customers, products, low-stock/unavailable products, recent orders; show only real API data.
- Products: CRUD, images, categories, variants, attributes/specifications, pricing, compare-at price, inventory/availability, SEO fields, publish status.
- Categories: CRUD, hierarchy, image, slug, SEO.
- Orders: list, details, status changes, payment status, fulfilment/tracking, refund status where supported.
- Customers: view/search, status, order history; avoid exposing unnecessary sensitive data.
- Reviews: moderation and verified-purchase labels.
- Pages/content: homepage sections, static pages, FAQs, policies, banners.
- Media/library: upload and manage images/logos.
- Store Settings: store name, logo, favicon, brand colors, contact info, currency, locale, canonical frontend URL, active API base URL, SEO defaults, shipping/returns text, social links, feature toggles.
- Payment Settings: enable/disable Stripe and PayPal, test/live mode, public IDs where appropriate, secret keys entered into secure fields and sent only over HTTPS to Laravel. After saving, never return stored secrets to the browser; show only “configured” status and masked last characters if appropriate.
- User/role management if supported by backend.
- Audit/log view for security-relevant configuration changes if provided by API.

All admin forms need validation, saving indicators, success/error feedback, and confirmation for destructive actions. Do not store payment secrets in browser storage or frontend config files.

### 10. Engineering and quality
- Build reusable components: header, footer, navigation, product card, price display, rating display, gallery, variant selector, quantity selector, cart drawer, breadcrumbs, accordions, review list, empty/error/loading states, admin layout, form fields.
- Use typed API response models matching Laravel contracts; coordinate exact shapes with backend task.
- Use environment variables for local bootstrap and public settings; include `.env.example` with placeholders only.
- Include setup documentation and commands.
- Include lint/type-check/build scripts and fix errors.
- Add tests for key flows where practical: product page, cart calculations, checkout states, protected admin routes, settings form.
- SEO: metadata, canonical URLs, Open Graph, sitemap/robots where appropriate, JSON-LD Product only with truthful fields.
- Performance: responsive image sizing, lazy loading below fold, avoid unnecessary client-side bundles, accessible interactions.
- Do not commit secrets, real credentials, or production `.env` files.

### 11. Local development and deployment
- Local frontend default: `http://localhost:3000`.
- Laravel API default: `http://127.0.0.1:8000` (or document the chosen local host consistently).
- Use the existing MySQL database `happykoop` through Laravel `.env`; the frontend must never connect directly to MySQL.
- Vercel is for the Next.js frontend. Laravel needs a separate PHP-capable host/container/VPS/platform; do not assume Vercel natively hosts a conventional Laravel app.
- Document production environment variables and CORS/cookie settings for the selected deployment topology.
- No live deployment or credential changes unless explicitly requested.

### 12. Deliverables
- Working Next.js frontend source.
- `.env.example`.
- Typed API client and response types.
- Responsive storefront, customer account and admin UI.
- Setup/README with local run instructions.
- A list of backend API contracts used and any blockers/questions.
- Do not silently stub essential features; clearly mark any unimplemented integration.
