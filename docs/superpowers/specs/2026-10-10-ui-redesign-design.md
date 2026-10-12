# UI Redesign: Design Spec

Date: 2026-10-10. Status: awaiting review.

## Goal

Redesign the whole app (layouts, shared components, all 53 pages) to match the
Qayema dashboard look: gold-on-black, serif headings, bordered cards, sectioned
sidebar. Source of truth for colours, fonts, shadows and motion is
`docs/UI_REDESIGN.md`. Behaviour, routes, props, i18n keys and PHP stay as they
are.

## Decisions (from the user)

- Light and dark themes with a header toggle. Light is the default.
- Package manager is **bun** (`bun install`, `bun run build`, `bun run dev`), not npm.
- Icons are **lucide-vue-next** (already a dependency). No new inline SVG path
  icon maps; the `getIcon` path table in `SidebarLayout.vue` is replaced.
- Approach: token-first, then a semantic-class sweep of every page.

## Findings that shape the work

- `package.json` has Tailwind **4.3.3** and `@tailwindcss/vite`, but
  `postcss.config.js`, `tailwind.config.js` and `app.css` are Tailwind v3 style
  (`@tailwind` directives, `tailwindcss: {}` PostCSS plugin). The CSS pipeline
  must be moved to v4 first (Phase 0). `CLAUDE.md` still says v3; update that line.
- About 1,100 `dark:` usages and hard-coded `gray-*`/`indigo-*` classes across
  `resources/js`. These become token classes.
- Existing i18n with `ar` and RTL (`dir` set in `resources/js/i18n/index.js`).
- `POS/Index.vue` (683 lines), `CartPanel.vue` and `ReceiptModal.vue` are the
  most intricate files.

## Architecture

### Phase 0: Tailwind v4 pipeline

- Add `@tailwindcss/vite` to `vite.config.js`; delete `postcss.config.js` and
  `tailwind.config.js`.
- `app.css` becomes `@import "tailwindcss";` plus `@plugin "@tailwindcss/forms";`,
  `@source` globs for `resources/js/**/*.vue` and Blade views, and a `@theme` block.
- Confirm `bun run build` succeeds before any visual work.

### Phase 1: Foundation

- `resources/css/app.css` holds every token from `docs/UI_REDESIGN.md`:
  brand, light and dark sets (`:root` and `html[data-theme='dark']`), status
  colours with `-wash` and `-border`, focus rings, photo overlays, shadows,
  radii, easing.
- `@theme inline` exposes tokens as utilities: `bg-bg`, `bg-surface`,
  `bg-field`, `text-fg`, `text-muted`, `text-faint`, `border-line`,
  `border-line-strong`, `text-accent`, `bg-accent-fill`, `bg-accent-wash`,
  `border-accent-border`, `text-status-*`, `bg-status-*-wash`,
  `shadow-pop`, `shadow-lift`, `rounded-field`, `rounded-control`,
  `font-display`, `font-sans`, `font-ar`.
- `app.blade.php`: Google Fonts (Instrument Serif 400/italic, Geist 300-700,
  El Messiri 400-700) replacing Bunny Figtree; inline script sets
  `data-theme` from localStorage before paint; body uses token classes.
  `html[lang='ar']` switches to El Messiri; Geist features `cv11`, `ss01` on.
- `resources/js/Composables/useTheme.js`: `theme` ref, `toggleTheme()`,
  persists to localStorage (try/catch), sets `data-theme`.

### Phase 2: Shell

- `SidebarLayout.vue` rebuilt: logo, sections with uppercase labels, gold-wash
  active item with a leading accent bar, lucide icons, bottom Collapse control,
  count badge support, mobile drawer. Navigation data and permission filtering
  logic are unchanged.
- Header: serif page title plus subtitle, branch switcher, `LanguageToggle`,
  new `ThemeToggle`, user avatar dropdown.
- `AuthenticatedLayout.vue` and `GuestLayout.vue` restyled. Logical properties
  only (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`) so RTL keeps working.

### Phase 3: Primitives (`resources/js/Components`)

Restyle: `PrimaryButton` (gold fill, ink text), `SecondaryButton`,
`DangerButton`, `TextInput`, `InputLabel`, `InputError`, `Checkbox`, `Modal`,
`Dropdown`, `DropdownLink`, `NavLink`, `ApplicationLogo`.

New, reused by pages: `PageHeader` (serif title, count chip, description,
actions slot), `Card`, `StatusBadge` (status colour props), `EmptyState`,
`ThemeToggle`, `StatCard`. Search for an existing component before adding one.

### Phase 4: Pages, in order

1. Dashboard
2. List pages: Products, Categories, Orders, Customers, Suppliers, Purchases,
   Inventory, Users, Units, Branches, Locations, ExchangeRates, Admin/Companies
3. Create/Edit forms
4. Reports
5. Settings, Profile
6. Welcome, Auth
7. POS (`Index`, `CartPanel`, `ReceiptModal`). The receipt/print output keeps
   a white background and dark text regardless of theme.

Per page: replace colour pairs with token classes, drop `dark:` variants that a
token now covers, use `PageHeader`/`Card`/`StatusBadge`, swap any inline SVG
icons for lucide. No prop, emit, route or logic changes.

### Phase 5: Cleanup

- `grep` for remaining `gray-`, `indigo-`, `dark:` and hex colours in
  `resources/js`; each hit is migrated or justified.
- `CLAUDE.md` versions and tooling notes were updated (done).

## Testing and verification

- New Pest feature test: Inertia pages still render (smoke test of key routes,
  reusing existing factories and any existing page tests).
- New token contrast test: parse the token values from `app.css` and assert
  4.5:1 for every text-on-surface pair listed in the doc, both themes (as the
  doc requires). Implemented in Pest (PHP) to avoid adding a JS test runner.
- After each phase: `bun run build` and the affected tests with
  `php artisan test --compact`.
- Manual check in the browser: Dashboard, one list, one form, POS, in light,
  dark and Arabic RTL. Report anything not verified.
- `vendor/bin/pint --dirty` for any PHP touched.

## Out of scope

Back-end changes, new features, new routes, copy changes beyond what a
component needs, and changing i18n keys. The Qayema-specific screens
(menu, QR, tables) are not ported; only their visual language is.

## Risks

- Tailwind v3 to v4 migration may expose other breakage (renamed utilities,
  default border colour now `currentColor`, `ring` default width). Phase 0
  gates the rest.
- POS is large; migrate it last and check it manually.
- Fonts load from Google Fonts at runtime (as the doc specifies); offline use
  falls back to the system stack.
