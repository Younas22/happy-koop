# HappyKoop — Header & Footer Redesign Task

## 0. Where to run this
Work in the **frontend repo**: `E:\sv26\htdocs\happy-koop-front` (Next.js App Router, TypeScript, Tailwind v4).
The Laravel API repo is `E:\sv26\htdocs\happy-koop` — read it only to confirm payload shapes. Do not change any
API contract for this task; everything asked for below is already served by existing endpoints.

Read first: `FRONTEND_TASK.md` (sections 3, 4, 6, 10) in the backend repo, and `src/app/globals.css`.

## 1. Goal
Redesign the storefront **header and footer** to a professional, modern, premium-commerce standard that is
**fully responsive** and **fully dynamic** — every label, link, logo, colour and block comes from the backend,
nothing hardcoded.

This is an **upgrade of existing components, not a greenfield build**. These already exist and must be
refactored rather than replaced wholesale:

| File | What it is today |
|------|------------------|
| `src/components/layout/SiteHeader.tsx` | Server component: brand, `navigation.primary` pills, inline search form, actions island |
| `src/components/layout/AppBar.tsx` | Client: sticky top bar that lifts to an elevated tonal surface on scroll |
| `src/components/layout/HeaderActions.tsx` | Client island: mobile menu drawer, search dialog, cart drawer, account menu |
| `src/components/layout/MobileNavBar.tsx` | Client: phone-only bottom nav bar (Home/Shop/Search/Cart/Account), hidden on `/checkout` and `/admin` |
| `src/components/layout/BrandLogo.tsx` | Shared logo, light/dark variants, text fallback |
| `src/components/layout/SiteFooter.tsx` | Async server component: brand, `navigation.footer` groups, auto-listed unlinked pages, contact block |
| `src/app/layout.tsx` | Composes header, degraded notice, footer, bottom nav bar |

Keep the Material-3-inspired foundation and the existing token system. Do **not** introduce a second design
language, a UI kit, an icon pack, or any new dependency.

## 2. Design direction (FRONTEND_TASK.md section 3 — non-negotiable)
- Clean, modern, premium ecommerce with a subtle material-inspired feel: restrained surfaces, clear hierarchy,
  comfortable spacing, soft borders, purposeful elevation.
- **No gradients.** No noisy animation, no marquees, no fake urgency, no countdowns, no invented badges.
- Light neutral base, dark readable text, restrained accent. The accent is **admin-controlled**
  (`theme.primaryColor`, `theme.accentColor`, `theme.radius`) — never hardcode a brand colour.
- Must look correct in **both light and dark** schemes.
- Product-first and mobile-first.

## 3. Use the existing design system — do not invent styles
Tokens and utilities already defined in `src/app/globals.css`. Reuse them instead of raw hex, raw shadows or
ad-hoc spacing:
- Surfaces: `surface`, `surface-low`, `surface-high`, `surface-highest`, `surface-container`, `canvas`, `sunken`
- Text: `ink`, `ink-muted`, `ink-subtle`, `ink-inverse`
- Brand: `brand`, `brand-hover`, `brand-active`, `brand-ink`, `brand-container`, `brand-on-container`,
  `brand-tint`, `brand-surface`
- Lines: `line`, `line-strong`
- Type scale: `text-label-sm|md|lg`, `text-body-sm|md|lg`, `text-title-*`, `label-eyebrow`
- Elevation: `elevation-0` … `elevation-5`. Motion: `ease-standard`, `ease-emphasized`, `ease-out-soft`
- Utilities: `page-container`, `state-layer`, `tap-target`, `surface-card`, `surface-panel`, `chip`, `chip-rail`,
  `pb-nav-bar`, `bottom-nav-bar`, `skeleton`, `sr-only-focusable`
- Radius: `--radius-xs|sm|md|lg|xl|2xl`, driven by the admin `theme.radius` key

If a token is genuinely missing, add it to `globals.css` with both light and dark values — never inline a literal.

## 4. The only data you may render (exact field names)
Everything below already exists. Rendering anything not in this list, or inventing placeholder copy, is a failure.

**Runtime config** — `ResolvedRuntimeConfig` from `getRuntimeConfig()` (`src/lib/runtime-config.ts`), typed in
`src/lib/types/api.ts`:
- `store`: `name`, `tagline`, `logoUrl`, `logoDarkUrl`, `faviconUrl`, `email`, `phone`, `addressLines[]`
- `theme`: `primaryColor`, `accentColor`, `radius`
- `locale`: `currency`, `currencySymbol`, `minorUnitExponent`, `locale`, `timezone`
- `policies`: `shippingSummary`, `returnsSummary` — used on the product page today, **not in the footer**
- `features`: `reviews`, `wishlist`, `newsletter`, `guestCheckout`, `search`, `publicStockCounts`
- `payments`: `stripe.enabled`, `stripe.mode`, `paypal.enabled`, `paypal.mode`
- `social`: `instagram`, `facebook`, `tiktok`, `x` — any may be null
- `navigation`: `primary: { label, href }[]`, `footer: { heading, links: { label, href }[] }[]`
- `degraded`, `apiBaseUrlRejected`

**Catalog** (`src/lib/catalog/queries.ts`):
- `fetchCategories({ tree: true })` → `Category[]` with `name`, `slug`, `imageUrl`, `productCount`, `children[]`
- `fetchPages()` → `PageSummary[]` (published pages only)

**Client state**: `selectCartCount` / `useCartStore` (`src/lib/cart/store.ts`), plus the existing `AccountMenu`
and `SearchDialog` components.

**Truthfulness rules — these decide whether the work is acceptable:**
- Every block is conditional on its data. No empty headings, no `info@example.com`, no placeholder address,
  no social icon for a handle that is null.
- `features.newsletter` is only a flag: **there is no subscribe endpoint.** Either omit the newsletter block,
  or build it and state plainly that it needs a backend endpoint first. Never ship a form that silently
  discards the address.
- `features.wishlist` is likewise only an admin toggle: **there is no wishlist route and no wishlist API.**
  Do not add a wishlist entry point to the header. Mention the gap instead.
- Payment badges only for providers whose `enabled` is true. Do not draw card-brand logos you cannot verify.
- When `config.degraded` is true the store details are neutral fallbacks — do not present them as real data.

## 5. Header requirements
**Desktop (`md` and up)**
1. Three-zone bar: brand (left), navigation (centre, flexible), actions (right). It must not wrap or overflow at
   1024px with 12 primary links — degrade with truncation or an overflow menu rather than breaking the row.
2. **Category mega menu** — the main new capability. One primary destination (e.g. "Shop") opens a panel built
   from `fetchCategories({ tree: true })`: top-level categories as columns, children as links, `imageUrl` as an
   optional visual, `productCount` where it helps. Fetch the tree **server-side** and pass it in — no
   client-side fetch waterfall and no spinner inside the header.
3. Keep the search affordance, and keep `features.search === false` hiding it completely.
4. Cart with live count badge, and the account menu.
5. Active-route styling via `aria-current="page"` plus a visual indicator, including for section prefixes.
6. Optional slim utility bar above the main bar, **only** when real content exists for it
   (`policies.shippingSummary`, or contact details). Never a hardcoded promo line.
7. Preserve `AppBar`'s sticky + scroll-elevation behaviour: passive listener, no layout work on scroll.

**Mobile (below `md`)**
8. Compact bar: menu, brand, search, cart. Thumb-reachable, minimum 44x44 targets (`tap-target`).
9. Drawer navigation with the **category tree as a nested accordion**, plus `navigation.primary`, account links
   and contact details. One level of nesting, no horizontal scrolling.
10. `MobileNavBar` keeps its current behaviour: hidden on `/checkout` and `/admin`, still reserving its own
    height so no page content is covered, and respecting `env(safe-area-inset-bottom)`.

## 6. Footer requirements
1. Brand column: logo (light/dark aware), `tagline`, and social links for configured handles only. Lucide ships
   no brand glyphs — keep honest text links or add inline SVGs; never a look-alike icon for the wrong network.
2. `navigation.footer` groups as columns, each with its `heading` and an `aria-label`.
3. Keep the existing "unlinked published pages" logic (`unlinkedPages()` in `SiteFooter.tsx`): pages the curated
   navigation does not already link, capped, then an "All pages" link.
4. Contact block: `email` as `mailto:`, `phone` as `tel:`, `addressLines` inside `<address>`.
5. **New:** policies block from `policies.shippingSummary` / `policies.returnsSummary` when present.
6. **New:** payment/trust row driven by `payments.*.enabled`, plus a locale line from `locale.currency`.
7. Bottom bar: `© {year} {store.name}`, plus legal links if they exist in `navigation.footer`.
8. Collapsible columns on phones (accordion) so the footer is not a 2000px wall; expanded from `sm` up.
9. Every column hides itself when empty. The footer must render correctly for a brand-new store where
   `navigation.footer` is `[]` and there is no tagline, no social and no contact.

## 7. Required follow-on: make navigation truly admin-editable
The API already reads **and writes** `navigation.primary` / `navigation.footer`
(`app/Http/Controllers/Api/V1/Admin/StoreSettingsController.php`, validated in
`app/Http/Requests/Admin/StoreSettingsRequest.php`), but the admin UI has **no editor for them**. Today the
header and footer links are whatever `database/seeders/SettingsSeeder.php` seeded, and an admin cannot change
them. A "dynamic" header and footer is not done until this exists.

Add a navigation editor to the admin — its own screen under `/admin/settings`, or a section of
`StoreSettingsForm.tsx` — honouring the server's limits exactly:
- `navigation.primary`: max 12 items; each `label` max 60 chars, `href` max 255, both required
- `navigation.footer`: max 6 groups; each `heading` max 60; each group max 12 links with the same label/href rules
- Add, remove and reorder rows, with validation, a saving indicator, success/error feedback, confirmation for
  destructive actions, and a hint that links may point at `/shop`, `/category/{slug}` or `/pages/{slug}`.

## 8. Engineering constraints
- Server components by default; interactivity confined to small client islands. The brand mark and the
  navigation must stay in the server-rendered shell.
- No new npm dependencies. Reuse `Drawer`, `Dialog`, `DropdownMenu`, `Accordion`, `Button` from
  `src/components/ui`, and `cn()` from `src/lib/utils/cn.ts`.
- `next/image` for all remote images, with correct `sizes`. Remote hosts are restricted by
  `next.config.ts` `images.remotePatterns` — do not loosen it.
- No layout shift: reserve logo and bar heights.
- Accessibility: full keyboard operation (mega menu opens and dismisses with Escape, focus returns to the
  trigger), visible `focus-visible` rings, `aria-current`, labelled landmarks (`<header>`, `<nav aria-label>`,
  `<footer>`), accessible names on every icon-only button, correct heading levels, AA contrast in both schemes.
- Respect `prefers-reduced-motion`.
- No secrets in client code or `NEXT_PUBLIC_*` variables.

## 9. Responsive acceptance
Verify at **360, 414, 768, 1024, 1280 and 1536 px**:
- No horizontal page scroll; 16px minimum side gutters.
- Header fits on one row from `md` up; the mobile bar never wraps.
- The mega menu panel never exceeds the viewport and never clips off-screen.
- Footer reflows 1 → 2 → 4/5 columns.
- The bottom nav bar never covers page content and never overlaps the checkout action bar.

## 10. Definition of done
- `npm run lint` clean, `npx tsc --noEmit` clean, `npm run build` passes.
- `npx vitest run` passes. Add tests where practical, e.g. the footer renders nothing for an empty config, and
  the mega menu builds correctly from a category-tree fixture.
- A brand-new store with empty navigation, no logo, no social and no contact still renders a correct,
  unbroken header and footer.
- Report honestly at the end: what was built, what was skipped, and anything (newsletter signup, wishlist) that
  needs backend work before it can function. Do not describe unverified work as finished.
