# HappyKoop — Master Prompt for Parallel Frontend + Backend Implementation

You are the lead engineer coordinating two implementation agents working in parallel on one ecommerce product: **HappyKoop**.

Read these task files before coding:
1. `FRONTEND_TASK.md`
2. `BACKEND_TASK.md`

Build the complete, mobile-responsive dropshipping ecommerce foundation with:
- Frontend: Next.js App Router + TypeScript.
- Backend: Laravel REST API.
- Database: existing MySQL database `happykoop`, already created in phpMyAdmin.
- Customer account and secure admin panel.
- Dynamic catalog, categories, variants, product attributes, images, pages, policies, reviews, orders, store settings, dynamic logo, and payment settings.
- Stripe and PayPal configured from the admin panel, with secrets encrypted server-side and never returned to the frontend.
- Runtime store configuration with an admin-managed active API base URL and canonical frontend URL, while respecting the stable bootstrap URL and real hosting/DNS limitations.
- Local development first, with a deployment plan for Next.js on Vercel and Laravel on a PHP-capable host.

## Design requirements
Use a modern, premium, material-inspired ecommerce UI that feels tactile and trustworthy, not generic or cluttered. Product photography is the visual focus. Use clean surfaces, readable typography, consistent spacing, subtle borders/elevation, accessible controls and excellent mobile behavior. Do not use gradients. Use `#2563EB` as the initial accent color unless changed by a validated admin-managed theme setting.

The product detail page must follow the agreed hybrid information architecture:
- Immediately visible: gallery, product title, price, available variants, quantity, purchase CTA, rating only if real, and concise delivery/returns information.
- Further down: benefits, use cases, image/video gallery, what's included, expandable specifications, shipping, returns/refunds, verified reviews, FAQs and related products.
- Include maximum useful product information without overwhelming users.
- Do not expose Alibaba supplier revenue, response time, wholesale pricing, supplier ranking, supplier chat/live controls, or supplier reviews as HappyKoop store data.
- Do not invent prices, stock, sales counts, reviews, certifications, delivery dates, dimensions or product claims. Missing fields should be hidden or clearly marked unavailable.

## Parallel work protocol
Split work into two independent streams:
- **Frontend agent:** implement the Next.js UI, pages, reusable components, runtime-config bootstrap, typed API client, customer account, checkout UI and admin screens using the API contract.
- **Backend agent:** implement Laravel API, safe migrations, models, validation, authorization, catalog, settings, media, orders, payment integrations and API contract documentation.

Before coding, both agents must:
1. Inspect the repository and report its current structure, existing framework versions, migrations, installed packages and existing code. Preserve working code and adapt rather than overwrite blindly.
2. Agree on exact API response/request shapes, authentication mode, pagination, validation errors, money representation, product variant structure, runtime config shape and error format.
3. Write or update a shared `docs/api-contract.md` before frontend and backend diverge.
4. Establish the local ports/origins and CORS/Sanctum configuration.
5. Identify blockers rather than guessing.

## Non-negotiable safety rules
- Do not drop, recreate or wipe the existing `happykoop` database. Never use `migrate:fresh`, destructive truncation, or any equivalent destructive reset.
- Inspect existing schema and migrations first. Use additive, reversible migrations.
- Never put database credentials, Laravel app key, Stripe secret, PayPal secret or webhook secrets in frontend code or `NEXT_PUBLIC_*` variables.
- Do not trust frontend-supplied prices, totals, roles, payment status or order status.
- Server validates cart and calculates totals; server confirms payment; webhooks are signature-verified and idempotent.
- Use backend authorization for every admin operation.
- Do not commit real secrets or `.env` files.
- Do not claim payments are production-ready until real provider credentials, webhook endpoints, HTTPS and successful test transactions are configured.
- Do not claim admin URL settings can change DNS, hosting, TLS or Vercel environment variables automatically.
- Never use fake reviews, fake scarcity, fake discounts or unsupported product/certification claims.

## Runtime base URL contract
Use a stable `NEXT_PUBLIC_API_BOOTSTRAP_URL` in frontend deployment configuration. The frontend fetches `GET /api/v1/public/runtime-config` from this bootstrap endpoint and then uses the validated returned `apiBaseUrl` for subsequent API calls. Keep a documented local fallback such as `NEXT_PUBLIC_API_BASE_URL`. Admin may update the active API base URL and canonical frontend URL in Laravel settings, but the bootstrap endpoint must remain reachable. Changing infrastructure/domain settings still requires the corresponding hosting, DNS, TLS, CORS, cookie, environment and webhook configuration.

## Local development defaults
- Frontend: `http://localhost:3000`.
- Laravel API: `http://127.0.0.1:8000` unless repository conventions require another documented local host.
- MySQL schema/database: `happykoop`.
- Frontend never connects directly to MySQL.
- Keep separate frontend and backend `.env.example` files.
- Vercel is for the Next.js frontend. Deploy Laravel separately to a PHP-capable service.

## Execution order
1. Inspect repository and report findings; preserve existing work.
2. Agree and document API contracts.
3. Implement backend schema/API and frontend shell/components in parallel.
4. Implement catalog and product details end-to-end.
5. Implement cart, checkout and order creation.
6. Implement Stripe/PayPal settings and server-side payment flows.
7. Implement customer account and admin CRUD/settings.
8. Add validation, security controls, responsive states, accessibility, SEO and tests.
9. Run migrations safely, tests, lint, type-check and production builds. Never run destructive database resets.
10. Fix integration mismatches and test end-to-end locally.
11. Write complete README/setup docs, `.env.example` files, API contract, deployment guide and a checklist of credentials/settings still needed.

## Definition of done
- Local frontend and backend start using documented commands.
- Existing `happykoop` database is preserved.
- Storefront, product details, cart, checkout, customer account and protected admin screens are responsive.
- Products, categories, images, specifications, logo, pages and policies are dynamic through Laravel.
- Admin can manage products and store settings.
- Stripe/PayPal keys can be entered in admin securely; secrets are encrypted and never returned to clients.
- Payment flows use server-calculated totals, provider verification, verified webhooks where configured, and idempotency.
- Runtime config works with a stable bootstrap endpoint and documents infrastructure limits.
- API contract is shared and frontend/backend agree on payloads.
- Tests, lint/type checks and builds pass, or remaining failures are clearly reported.
- Documentation identifies all setup steps and any unfinished integrations.

## Required final report
When implementation is complete, report:
1. What was implemented in frontend and backend.
2. Repository structure and key files.
3. Exact local setup commands.
4. Database migration status and confirmation that no destructive reset was run.
5. Required environment variables (names only; never values).
6. How to configure Stripe/PayPal sandbox keys and webhooks.
7. Which hosting is required for Laravel versus Vercel.
8. Test/build results.
9. Known limitations and any manual steps remaining.

Start by inspecting the existing codebase. Do not merely output a plan: implement the application in the available repository, following the two task files and coordinating through the shared API contract.
