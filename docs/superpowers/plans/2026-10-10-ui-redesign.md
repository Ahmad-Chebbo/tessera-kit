# UI Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the whole Swipe n Sell app (layouts, shared components, 53 pages, POS) to the Qayema dashboard look, with light and dark themes.

**Architecture:** Move CSS to Tailwind v4 (CSS-first). Put every token from `docs/UI_REDESIGN.md` in `resources/css/app.css` as CSS variables, switched by `html[data-theme]`, and expose them as utilities through `@theme inline`. Rebuild shell and primitives on the tokens, then migrate pages in batches. A Pest guard test lists migrated files and fails on any hard-coded colour, `dark:` variant or physical-direction class, so each batch goes red then green.

**Tech Stack:** Laravel 13, Inertia (server v3, `@inertiajs/vue3` 2.3), Vue 3, Tailwind 4.3 via `@tailwindcss/vite`, Vite 8, lucide-vue-next, Pest 4, **bun**.

**Spec:** `docs/superpowers/specs/2026-10-10-ui-redesign-design.md`. Colour, font, shadow and motion values: `docs/UI_REDESIGN.md`.

## Global Constraints

- Package manager is **bun**: `bun install`, `bun run build`, `bun run dev`. Never npm.
- Icons come only from `lucide-vue-next`. No inline SVG icon paths for UI icons.
- Components use tokens (`bg-surface`, `text-muted`, `border-line`, `text-accent`), never hex values or raw palette classes (`gray-*`, `indigo-*`, `bg-white`).
- No `dark:` variants in migrated files; tokens switch with the theme.
- RTL must keep working: logical utilities only (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`, `text-start`, `text-end`); no `ml-`, `mr-`, `pl-`, `pr-`, `left-`, `right-`, `text-left`, `text-right`.
- Light is the default theme; the toggle persists to `localStorage` key `theme` (values `light`, `dark`).
- Tailwind v4 renames to respect: `outline-none` is `outline-hidden`, `flex-shrink-*` is `shrink-*`, `bg-opacity-*`/`ring-opacity-*` are gone (use `bg-black/50`), default border colour is `currentColor` (the base layer in `app.css` sets it to `--line`), default `ring` width is 1px.
- Do not change props, emits, routes, Inertia data, i18n keys' meanings, or PHP. Only add i18n keys where a new control needs a label.
- Tokens utilities: the `--text` token is exposed as `text-fg` / `bg-fg` (not `text-text`); the spec's `text-text` is superseded by this.
- Fonts: Instrument Serif (headings, 400 and italic only, so headings use `font-normal`), Geist (body), El Messiri (Arabic, `html[lang='ar']`).
- Run `vendor/bin/pint --dirty` after touching any PHP.
- Commit message trailer: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Blocked/corrupt `localStorage`** (private window, `theme` = garbage): the app must fall back to light and never throw. Pinned in Task 2 (Blade script asserted to use `try/catch` and a strict `=== 'dark'` check) and the `useTheme` code.
2. **Arabic RTL**: sidebar slide-in direction, collapse chevrons, dropdown alignment. Pinned by the guard test's physical-direction ban (Task 3 onward) and a manual RTL check in Tasks 5 and 15.
3. **Receipt printing**: `ReceiptModal` must print dark-on-white in either theme. Pinned in Task 14 with an explicit `allow-colour` exception and a manual print-preview check.
4. **User with few permissions** (nav sections all filtered out, no `currentBranch`): the sidebar and header must render without empty sections or errors. The existing filter logic is kept; Task 5 verifies with a restricted user.
5. **Long strings** (product names, Arabic names, long emails in the header): must truncate, not break layout. Pinned in Tasks 5 and 6 with `truncate`/`min-w-0` and a manual check.

---

## Migration rules (used by Tasks 7-14)

Replace classes by role. Left column patterns are what the pages use today.

| Today | Use |
|---|---|
| `bg-gray-100`, `bg-gray-50` (page background) | `bg-bg` |
| `bg-white dark:bg-gray-800` (cards, panels, modals) | `bg-surface` |
| `bg-gray-50`/`bg-gray-100` + `dark:bg-gray-700`/`-900` (inputs, table heads, wells) | `bg-field` (inputs) or `bg-hover-wash` (wells, table heads) |
| `text-gray-900 dark:text-white`, `text-gray-800` | `text-fg` |
| `text-gray-700`, `text-gray-600`, `text-gray-500` (+dark pairs) | `text-muted` |
| `text-gray-400` (+dark pair), `text-slate-*` | `text-faint` |
| `border-gray-200`, `border-gray-300`, `divide-gray-200` (+dark pairs) | `border-line` / `divide-line` (`border-line-strong` for inputs on hover/focus rings) |
| `bg-indigo-600 text-white hover:bg-indigo-700` (primary action) | `bg-gold text-ink hover:bg-gold-deep` (or use `PrimaryButton`) |
| `text-indigo-600 hover:text-indigo-800` (links) | `text-accent hover:underline` |
| `bg-indigo-50`/`bg-indigo-100` (selected, active) | `bg-accent-wash` with `border-accent-border` |
| `focus:ring-indigo-500`, `focus:border-indigo-500` | `focus:ring-ring-accent focus:border-gold-on` |
| `bg-red-100 text-red-800`, `text-red-600` | `bg-status-danger-wash text-status-danger border border-status-danger-border` (or `StatusBadge status="danger"`) |
| yellow/amber → `warn`; green/emerald → `success`; blue/sky → `info` | same pattern with `status-warn`, `status-success`, `status-info` |
| `bg-red-600 text-white` (delete) | `bg-danger text-white` (or `DangerButton`) |
| `shadow`, `shadow-sm`, `shadow-md` on cards | none (cards use `border border-line`); popups `shadow-pop`; raised hover cards `shadow-lift` |
| `rounded-md`/`rounded-lg` on cards | `rounded-2xl` (cards), `rounded-control` (buttons), `rounded-field` (inputs) |
| page `<h1>`/`<h2>` titles | `PageHeader` (title, optional `count`, `description`, `#actions` slot); section titles inside cards use `Card` `title` prop |
| stat blocks (label + big number) | `StatCard` |
| coloured status pills | `StatusBadge` |
| "no data" blocks | `EmptyState` |
| inline `<svg>` icons | lucide component, `class="h-4 w-4"` (or `h-5 w-5`) |
| `ml-*`, `mr-*`, `pl-*`, `pr-*`, `left-*`, `right-*`, `text-left`, `text-right` | `ms-*`, `me-*`, `ps-*`, `pe-*`, `start-*`, `end-*`, `text-start`, `text-end` |
| `flex-shrink-0`, `bg-opacity-*`, `outline-none` | `shrink-0`, `/alpha` colour, `outline-hidden` |
| big numbers / currency totals | `font-display text-2xl` (Instrument Serif is regular weight only; drop `font-bold`) |

Lines that must keep a literal colour (printable receipt, barcode image) end with the marker `allow-colour` in a comment on the same line; the guard test skips those lines.

## Batch procedure (Tasks 7-14, spelled out once here and applied in each batch)

1. Add the batch's files to `MIGRATED` in `tests/Unit/MigratedFilesUseTokensTest.php`; run the guard and confirm it FAILS listing those files.
2. For each file: Read it; apply the Migration rules; introduce `PageHeader`/`Card`/`StatCard`/`StatusBadge`/`EmptyState` where the page has a title, panel, stat, pill or empty state; swap inline SVG for lucide. Keep script logic untouched.
3. Run the guard: `php artisan test --compact tests/Unit/MigratedFilesUseTokensTest.php`; expect PASS.
4. Run `bun run build`; expect success. If a lucide import fails ("is not exported"), look the icon up with `grep -o "export { default as [A-Za-z0-9]* " node_modules/lucide-vue-next/dist/esm/lucide-vue-next.js | grep -i <name>` and use the exact exported name.
5. Run `php artisan test --compact`; expect all PASS.
6. Open one page of the batch (URL from `get-absolute-url`) in light, dark and Arabic; fix anything unreadable or misaligned.
7. Commit the batch.

---

### Task 1: Tailwind v4 pipeline, tokens and contrast tests

**Files:**
- Create: `tests/Unit/ThemeTokensTest.php`
- Modify: `resources/css/app.css`, `vite.config.js`, `resources/js/app.js` (progress colour), `docs/superpowers/specs/2026-10-10-ui-redesign-design.md` (`text-text` to `text-fg`)
- Delete: `postcss.config.js`, `tailwind.config.js`
- Track: `docs/UI_REDESIGN.md`, `bun.lock`

**Interfaces:**
- Produces: CSS custom properties (`--bg`, `--surface`, `--field`, `--text`, `--muted`, `--faint`, `--placeholder`, `--line`, `--line-strong`, `--line-2`, `--hover-wash`, `--disabled-wash`, `--gold-on`, `--accent-text`, `--accent-fill`, `--accent-wash`, `--accent-wash-hover`, `--accent-border`, `--status-{danger,warn,success,info}` plus `-wash` and `-border`, `--ring-{accent,danger,success}`, `--overlay-pill`, `--overlay-chip`, `--overlay-chip-hover`, `--overlay-chip-ink`, `--pop-shadow`, `--lift-shadow`, brand `--gold`, `--gold-deep`, `--ink`, `--paper`, `--sand`, `--danger`).
- Produces utilities: `bg-bg bg-surface bg-field text-fg text-muted text-faint text-placeholder border-line border-line-strong border-line-2 bg-hover-wash bg-disabled-wash text-accent bg-accent-fill bg-accent-wash bg-accent-wash-hover border-accent-border text-gold-on border-gold-on bg-gold bg-gold-deep text-ink bg-danger text-status-* bg-status-*-wash border-status-*-border ring-ring-accent ring-ring-danger ring-ring-success bg-overlay-pill bg-overlay-chip text-overlay-chip-ink shadow-pop shadow-lift rounded-field rounded-control font-display font-sans font-ar font-mono ease-qayema`, and the `dark:` variant bound to `html[data-theme='dark']`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/ThemeTokensTest.php`:

```php
<?php

const THEME_CSS_PATH = __DIR__.'/../../resources/css/app.css';

const REQUIRED_TOKENS = [
    'bg', 'surface', 'field', 'text', 'muted', 'faint', 'placeholder', 'line', 'line-strong', 'line-2',
    'hover-wash', 'disabled-wash', 'gold-on', 'accent-text', 'accent-fill', 'accent-wash',
    'accent-wash-hover', 'accent-border',
    'status-danger', 'status-danger-wash', 'status-danger-border',
    'status-warn', 'status-warn-wash', 'status-warn-border',
    'status-success', 'status-success-wash', 'status-success-border',
    'status-info', 'status-info-wash', 'status-info-border',
    'ring-accent', 'ring-danger', 'ring-success',
    'overlay-pill', 'overlay-chip', 'overlay-chip-hover', 'overlay-chip-ink',
    'pop-shadow', 'lift-shadow',
    'gold', 'gold-deep', 'ink', 'paper', 'sand', 'danger',
];

/**
 * @return array<string, string>
 */
function themeBlockTokens(string $css, string $selector): array
{
    $pattern = '/(?:^|\n)'.preg_quote($selector, '/').'\s*\{(.*?)\n\}/s';
    preg_match($pattern, $css, $block);
    preg_match_all('/--([a-z0-9-]+):\s*([^;]+);/', $block[1] ?? '', $matches, PREG_SET_ORDER);

    $tokens = [];
    foreach ($matches as $match) {
        $tokens[$match[1]] = trim($match[2]);
    }

    return $tokens;
}

/**
 * @return array{0: float, 1: float, 2: float, 3: float}
 */
function themeParseColor(string $value): array
{
    if (preg_match('/^#([0-9a-f]{6})$/i', $value, $m)) {
        return [hexdec(substr($m[1], 0, 2)), hexdec(substr($m[1], 2, 2)), hexdec(substr($m[1], 4, 2)), 1.0];
    }

    if (preg_match('/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+)\s*)?\)$/', $value, $m)) {
        return [(float) $m[1], (float) $m[2], (float) $m[3], isset($m[4]) ? (float) $m[4] : 1.0];
    }

    throw new InvalidArgumentException("Unparseable colour: {$value}");
}

/**
 * @param  array{0: float, 1: float, 2: float, 3: float}  $foreground
 * @param  array{0: float, 1: float, 2: float, 3: float}  $background
 * @return array{0: float, 1: float, 2: float, 3: float}
 */
function themeBlend(array $foreground, array $background): array
{
    $alpha = $foreground[3];

    return [
        $alpha * $foreground[0] + (1 - $alpha) * $background[0],
        $alpha * $foreground[1] + (1 - $alpha) * $background[1],
        $alpha * $foreground[2] + (1 - $alpha) * $background[2],
        1.0,
    ];
}

/**
 * @param  array{0: float, 1: float, 2: float, 3: float}  $colour
 */
function themeLuminance(array $colour): float
{
    $channels = array_map(function (float $channel): float {
        $c = $channel / 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, array_slice($colour, 0, 3));

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

/**
 * @param  array<string, string>  $tokens
 */
function themeContrast(array $tokens, string $foreground, string $background, string $over = 'bg'): float
{
    $base = themeParseColor($tokens[$over]);
    $bg = themeBlend(themeParseColor($tokens[$background]), $base);
    $fg = themeBlend(themeParseColor($tokens[$foreground]), $bg);
    $lighter = max(themeLuminance($fg), themeLuminance($bg));
    $darker = min(themeLuminance($fg), themeLuminance($bg));

    return ($lighter + 0.05) / ($darker + 0.05);
}

dataset('themes', [
    'light' => [':root'],
    'dark' => ["html[data-theme='dark']"],
]);

it('defines every documented token in both themes', function (string $selector) {
    $css = file_get_contents(THEME_CSS_PATH);
    $tokens = themeBlockTokens($css, ':root');

    if ($selector !== ':root') {
        $tokens = array_merge($tokens, themeBlockTokens($css, $selector));
        expect(themeBlockTokens($css, $selector))->not->toBeEmpty();
    }

    foreach (REQUIRED_TOKENS as $name) {
        expect($tokens)->toHaveKey($name);
    }
})->with('themes');

it('keeps text colours at 4.5:1 or better on their surfaces', function (string $selector) {
    $css = file_get_contents(THEME_CSS_PATH);
    $tokens = array_merge(themeBlockTokens($css, ':root'), $selector === ':root' ? [] : themeBlockTokens($css, $selector));

    $pairs = [
        ['text', 'bg'], ['text', 'surface'], ['text', 'field'],
        ['muted', 'bg'], ['muted', 'surface'], ['muted', 'field'],
        ['faint', 'bg'], ['faint', 'surface'],
        ['accent-text', 'surface'], ['accent-text', 'bg'], ['accent-text', 'accent-wash', 'surface'],
        ['ink', 'gold'], ['ink', 'gold-deep'], ['ink', 'accent-fill'],
        ['overlay-chip-ink', 'overlay-chip', 'surface'],
    ];

    foreach (['danger', 'warn', 'success', 'info'] as $status) {
        $pairs[] = ["status-{$status}", 'surface'];
        $pairs[] = ["status-{$status}", "status-{$status}-wash", 'surface'];
    }

    foreach ($pairs as $pair) {
        $ratio = themeContrast($tokens, $pair[0], $pair[1], $pair[2] ?? 'bg');
        expect($ratio)->toBeGreaterThanOrEqual(4.5, "{$pair[0]} on {$pair[1]} is {$ratio}");
    }
})->with('themes');
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Unit/ThemeTokensTest.php`
Expected: FAIL (tokens missing from `app.css`).

- [ ] **Step 3: Move the pipeline to Tailwind v4**

Replace `vite.config.js`:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
```

Run: `git rm postcss.config.js tailwind.config.js`

In `resources/js/app.js` change `color: '#4B5563'` to `color: '#C39B50'`.

- [ ] **Step 4: Write `resources/css/app.css`**

```css
@import "tailwindcss";
@plugin "@tailwindcss/forms";

@source "../views";
@source "../js";

@custom-variant dark (&:where([data-theme='dark'], [data-theme='dark'] *));

:root {
    --gold: #F8D38D;
    --gold-deep: #E9BD66;
    --ink: #141109;
    --paper: #FBFAF6;
    --sand: #E8DCCB;
    --danger: #B45040;

    --bg: #FBFAF6;
    --surface: #FFFFFF;
    --field: #FBFAF6;
    --text: #141109;
    --muted: rgba(20, 17, 9, 0.62);
    --faint: rgba(20, 17, 9, 0.60);
    --placeholder: rgba(20, 17, 9, 0.32);
    --line: rgba(20, 17, 9, 0.10);
    --line-strong: rgba(20, 17, 9, 0.18);
    --line-2: rgba(20, 17, 9, 0.06);
    --hover-wash: rgba(20, 17, 9, 0.04);
    --disabled-wash: rgba(20, 17, 9, 0.04);
    --gold-on: #B08A2C;
    --accent-text: #82631B;
    --accent-fill: #F8D38D;
    --accent-wash: rgba(233, 189, 102, 0.16);
    --accent-wash-hover: rgba(233, 189, 102, 0.24);
    --accent-border: rgba(233, 189, 102, 0.55);

    --status-danger: #A3483A;
    --status-danger-wash: rgba(163, 72, 58, 0.10);
    --status-danger-border: rgba(163, 72, 58, 0.30);
    --status-warn: #9A5B12;
    --status-warn-wash: rgba(154, 91, 18, 0.10);
    --status-warn-border: rgba(154, 91, 18, 0.30);
    --status-success: #506C46;
    --status-success-wash: rgba(80, 108, 70, 0.10);
    --status-success-border: rgba(80, 108, 70, 0.30);
    --status-info: #4A6B86;
    --status-info-wash: rgba(74, 107, 134, 0.10);
    --status-info-border: rgba(74, 107, 134, 0.30);

    --ring-accent: rgba(248, 211, 141, 0.35);
    --ring-danger: rgba(180, 80, 64, 0.10);
    --ring-success: rgba(92, 122, 79, 0.12);

    --overlay-pill: rgba(20, 17, 9, 0.78);
    --overlay-chip: rgba(251, 250, 246, 0.92);
    --overlay-chip-hover: rgb(251, 250, 246);
    --overlay-chip-ink: #141109;

    --pop-shadow: 0 24px 60px -20px rgba(20, 17, 9, 0.35), 0 4px 12px rgba(20, 17, 9, 0.06);
    --lift-shadow: 0 12px 32px -8px rgba(20, 17, 9, 0.18), 0 2px 8px -2px rgba(20, 17, 9, 0.08);
}

html[data-theme='dark'] {
    --bg: #000000;
    --surface: #0A0A0A;
    --field: #111111;
    --text: #F5F1E8;
    --muted: rgba(245, 241, 232, 0.60);
    --faint: rgba(245, 241, 232, 0.55);
    --placeholder: rgba(245, 241, 232, 0.32);
    --line: rgba(245, 241, 232, 0.12);
    --line-strong: rgba(245, 241, 232, 0.22);
    --line-2: rgba(245, 241, 232, 0.06);
    --hover-wash: rgba(245, 241, 232, 0.06);
    --disabled-wash: rgba(245, 241, 232, 0.04);
    --gold-on: #F8D38D;
    --accent-text: #F8D38D;
    --accent-fill: #C39B50;
    --accent-wash: rgba(248, 211, 141, 0.12);
    --accent-wash-hover: rgba(248, 211, 141, 0.18);
    --accent-border: rgba(248, 211, 141, 0.35);

    --status-danger: #E8907F;
    --status-danger-wash: rgba(232, 144, 127, 0.14);
    --status-danger-border: rgba(232, 144, 127, 0.38);
    --status-warn: #F0B354;
    --status-warn-wash: rgba(240, 179, 84, 0.14);
    --status-warn-border: rgba(240, 179, 84, 0.38);
    --status-success: #A3C491;
    --status-success-wash: rgba(163, 196, 145, 0.14);
    --status-success-border: rgba(163, 196, 145, 0.38);
    --status-info: #93B8D6;
    --status-info-wash: rgba(147, 184, 214, 0.14);
    --status-info-border: rgba(147, 184, 214, 0.38);

    --ring-accent: rgba(248, 211, 141, 0.28);
    --ring-danger: rgba(180, 80, 64, 0.22);
    --ring-success: rgba(163, 196, 145, 0.22);

    --overlay-pill: rgba(0, 0, 0, 0.70);
    --overlay-chip: rgba(10, 10, 10, 0.78);
    --overlay-chip-hover: rgba(10, 10, 10, 0.92);
    --overlay-chip-ink: #F5F1E8;

    --pop-shadow: 0 24px 60px -20px rgba(0, 0, 0, 0.85), 0 0 0 0.5px rgba(245, 241, 232, 0.08);
    --lift-shadow: 0 12px 32px -8px rgba(0, 0, 0, 0.70), 0 2px 8px -2px rgba(0, 0, 0, 0.50);
}

@theme inline {
    --font-display: 'Instrument Serif', 'El Messiri', Georgia, 'Times New Roman', serif;
    --font-sans: 'Geist', 'El Messiri', ui-sans-serif, system-ui, sans-serif;
    --font-ar: 'El Messiri', 'Geist', system-ui, sans-serif;
    --font-mono: ui-monospace, 'JetBrains Mono', 'SF Mono', Menlo, monospace;

    --color-gold: var(--gold);
    --color-gold-deep: var(--gold-deep);
    --color-ink: var(--ink);
    --color-paper: var(--paper);
    --color-sand: var(--sand);
    --color-danger: var(--danger);

    --color-bg: var(--bg);
    --color-surface: var(--surface);
    --color-field: var(--field);
    --color-fg: var(--text);
    --color-muted: var(--muted);
    --color-faint: var(--faint);
    --color-placeholder: var(--placeholder);
    --color-line: var(--line);
    --color-line-strong: var(--line-strong);
    --color-line-2: var(--line-2);
    --color-hover-wash: var(--hover-wash);
    --color-disabled-wash: var(--disabled-wash);
    --color-gold-on: var(--gold-on);
    --color-accent: var(--accent-text);
    --color-accent-fill: var(--accent-fill);
    --color-accent-wash: var(--accent-wash);
    --color-accent-wash-hover: var(--accent-wash-hover);
    --color-accent-border: var(--accent-border);

    --color-status-danger: var(--status-danger);
    --color-status-danger-wash: var(--status-danger-wash);
    --color-status-danger-border: var(--status-danger-border);
    --color-status-warn: var(--status-warn);
    --color-status-warn-wash: var(--status-warn-wash);
    --color-status-warn-border: var(--status-warn-border);
    --color-status-success: var(--status-success);
    --color-status-success-wash: var(--status-success-wash);
    --color-status-success-border: var(--status-success-border);
    --color-status-info: var(--status-info);
    --color-status-info-wash: var(--status-info-wash);
    --color-status-info-border: var(--status-info-border);

    --color-ring-accent: var(--ring-accent);
    --color-ring-danger: var(--ring-danger);
    --color-ring-success: var(--ring-success);

    --color-overlay-pill: var(--overlay-pill);
    --color-overlay-chip: var(--overlay-chip);
    --color-overlay-chip-hover: var(--overlay-chip-hover);
    --color-overlay-chip-ink: var(--overlay-chip-ink);

    --shadow-pop: var(--pop-shadow);
    --shadow-lift: var(--lift-shadow);

    --radius-field: 12px;
    --radius-control: 10px;

    --ease-qayema: cubic-bezier(0.22, 0.61, 0.36, 1);
}

@layer base {
    *,
    ::before,
    ::after {
        border-color: var(--line);
    }

    html {
        background-color: var(--bg);
        color: var(--text);
    }

    body {
        font-family: var(--font-sans);
        font-feature-settings: 'cv11', 'ss01';
    }

    html[lang='ar'] body {
        font-family: var(--font-ar);
    }

    ::placeholder {
        color: var(--placeholder);
    }
}
```

- [ ] **Step 5: Fix the spec utility name, track the doc and lockfile**

In `docs/superpowers/specs/2026-10-10-ui-redesign-design.md` replace `text-text` with `text-fg`.

- [ ] **Step 6: Run the test and the build**

Run: `php artisan test --compact tests/Unit/ThemeTokensTest.php`
Expected: PASS (4 tests). If a contrast pair fails, the message names the pair and ratio: report it to the user instead of editing the doc's values.

Run: `bun install && bun run build`
Expected: build succeeds. Existing pages will look unstyled-wrong until later tasks (raw `gray-*` classes still exist in v4); that is expected.

- [ ] **Step 7: Commit**

```bash
git add vite.config.js resources/css/app.css resources/js/app.js tests/Unit/ThemeTokensTest.php docs/UI_REDESIGN.md docs/superpowers bun.lock
git commit -m "Move CSS to Tailwind v4 and add theme tokens with contrast tests

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Fonts, theme bootstrap and theme toggle

**Files:**
- Create: `resources/js/Composables/useTheme.js`, `resources/js/Components/ThemeToggle.vue`, `tests/Feature/ThemeShellTest.php`
- Modify: `resources/views/app.blade.php`, `resources/js/i18n/locales/en.json`, `resources/js/i18n/locales/ar.json`

**Interfaces:**
- Consumes: tokens and `dark:` variant from Task 1.
- Produces: `useTheme(): { theme: Ref<'light'|'dark'>, setTheme(value: 'light'|'dark'): void, toggleTheme(): void }`; `<ThemeToggle />` (no props); i18n keys `nav.toggleTheme`, `nav.collapse`, `nav.expand`, `nav.openMenu`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ThemeShellTest.php`:

```php
<?php

test('the app shell loads the brand fonts and bootstraps the theme safely', function () {
    $this->withoutVite();

    $this->get('/login')
        ->assertOk()
        ->assertSee('fonts.googleapis.com/css2', false)
        ->assertSee('Instrument+Serif', false)
        ->assertSee('family=Geist', false)
        ->assertSee('El+Messiri', false)
        ->assertSee("localStorage.getItem('theme') === 'dark'", false)
        ->assertSee('catch', false)
        ->assertSee('data-theme="light"', false);
});
```

- [ ] **Step 2: Run it; expect FAIL**

Run: `php artisan test --compact tests/Feature/ThemeShellTest.php`

- [ ] **Step 3: Replace `resources/views/app.blade.php`**

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <script>
            (function () {
                var theme = 'light';
                try {
                    theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
                } catch (error) {
                    theme = 'light';
                }
                document.documentElement.setAttribute('data-theme', theme);
            })();
        </script>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=El+Messiri:wght@400;500;600;700&family=Geist:wght@300;400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="bg-bg font-sans text-fg antialiased">
        @inertia
    </body>
</html>
```

- [ ] **Step 4: Create `resources/js/Composables/useTheme.js`**

```js
import { ref } from 'vue';

const STORAGE_KEY = 'theme';

function readStoredTheme() {
    try {
        return localStorage.getItem(STORAGE_KEY) === 'dark' ? 'dark' : 'light';
    } catch {
        return 'light';
    }
}

const theme = ref(readStoredTheme());

function applyTheme(value) {
    document.documentElement.setAttribute('data-theme', value);
}

applyTheme(theme.value);

export function useTheme() {
    function setTheme(value) {
        theme.value = value === 'dark' ? 'dark' : 'light';
        applyTheme(theme.value);

        try {
            localStorage.setItem(STORAGE_KEY, theme.value);
        } catch {
            // Storage can be blocked (private window); the theme still applies for this visit.
        }
    }

    function toggleTheme() {
        setTheme(theme.value === 'dark' ? 'light' : 'dark');
    }

    return { theme, setTheme, toggleTheme };
}
```

- [ ] **Step 5: Create `resources/js/Components/ThemeToggle.vue`**

```vue
<script setup>
import { Moon, Sun } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useTheme } from '@/Composables/useTheme';

const { t } = useI18n();
const { theme, toggleTheme } = useTheme();
</script>

<template>
    <button
        type="button"
        :aria-label="t('nav.toggleTheme')"
        :title="t('nav.toggleTheme')"
        class="inline-flex h-9 w-9 items-center justify-center rounded-control text-muted transition duration-150 ease-qayema hover:bg-hover-wash hover:text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-on"
        @click="toggleTheme"
    >
        <Sun v-if="theme === 'dark'" class="h-4 w-4" />
        <Moon v-else class="h-4 w-4" />
    </button>
</template>
```

- [ ] **Step 6: Add i18n keys**

In `resources/js/i18n/locales/en.json`, replace the line `    "nav": {` (first occurrence, 4-space indent) with:

```json
    "nav": {
        "toggleTheme": "Toggle theme",
        "collapse": "Collapse",
        "expand": "Expand",
        "openMenu": "Open menu",
```

In `resources/js/i18n/locales/ar.json` do the same with:

```json
    "nav": {
        "toggleTheme": "تبديل المظهر",
        "collapse": "طي القائمة",
        "expand": "توسيع القائمة",
        "openMenu": "فتح القائمة",
```

Confirm each file is still valid JSON: `php -r "json_decode(file_get_contents('resources/js/i18n/locales/en.json'), false, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents('resources/js/i18n/locales/ar.json'), false, 512, JSON_THROW_ON_ERROR); echo 'ok';"`

- [ ] **Step 7: Run test and build; expect PASS / success**

Run: `php artisan test --compact tests/Feature/ThemeShellTest.php` then `bun run build`.

- [ ] **Step 8: Commit**

```bash
git add resources/views/app.blade.php resources/js/Composables/useTheme.js resources/js/Components/ThemeToggle.vue resources/js/i18n tests/Feature/ThemeShellTest.php
git commit -m "Add brand fonts, theme bootstrap, useTheme and ThemeToggle

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Token guard test and restyled primitives

**Files:**
- Create: `tests/Unit/MigratedFilesUseTokensTest.php`
- Modify: `resources/js/Components/{PrimaryButton,SecondaryButton,DangerButton,TextInput,InputLabel,InputError,Checkbox,Modal,Dropdown,DropdownLink,NavLink,ResponsiveNavLink,LanguageToggle}.vue`

**Interfaces:**
- Produces: `MIGRATED` constant (list of paths relative to `resources/js`) in the guard test, which Tasks 4-14 extend. Restyled primitives keep their current props and emits exactly.

- [ ] **Step 1: Write the guard test**

Create `tests/Unit/MigratedFilesUseTokensTest.php`:

```php
<?php

const MIGRATED = [
    'Components/PrimaryButton.vue',
    'Components/SecondaryButton.vue',
    'Components/DangerButton.vue',
    'Components/TextInput.vue',
    'Components/InputLabel.vue',
    'Components/InputError.vue',
    'Components/Checkbox.vue',
    'Components/Modal.vue',
    'Components/Dropdown.vue',
    'Components/DropdownLink.vue',
    'Components/NavLink.vue',
    'Components/ResponsiveNavLink.vue',
    'Components/LanguageToggle.vue',
];

const FORBIDDEN_PATTERNS = [
    'raw palette colour' => '/\b(?:bg|text|border|ring|divide|from|to|via|fill|stroke|outline|placeholder|accent|caret|shadow)-(?:gray|slate|zinc|neutral|stone|indigo|blue|red|green|yellow|amber|emerald|purple|orange|rose|teal|cyan|sky|violet|pink|lime|fuchsia)-\d{2,3}\b/',
    'dark: variant' => '/\bdark:/',
    'white/black utility' => '/\b(?:bg|text|border|ring)-(?:white|black)\b/',
    'hex colour' => '/#[0-9a-fA-F]{3,8}\b/',
    'physical direction' => '/\b(?:ml|mr|pl|pr|left|right)-\d|\btext-(?:left|right)\b/',
    'removed v4 utility' => '/\b(?:ring|bg|border)-opacity-\d+|\bflex-shrink-|\boutline-none\b/',
];

it('keeps migrated files on theme tokens', function (string $file) {
    $path = __DIR__.'/../../resources/js/'.$file;
    expect(file_exists($path))->toBeTrue("{$file} does not exist");

    $violations = [];
    foreach (file($path) as $number => $line) {
        if (str_contains($line, 'allow-colour')) {
            continue;
        }

        foreach (FORBIDDEN_PATTERNS as $label => $pattern) {
            if (preg_match($pattern, $line, $match)) {
                $violations[] = sprintf('%s:%d %s (%s)', $file, $number + 1, $label, $match[0]);
            }
        }
    }

    expect($violations)->toBe([]);
})->with(MIGRATED);
```

- [ ] **Step 2: Run it; expect FAIL**

Run: `php artisan test --compact tests/Unit/MigratedFilesUseTokensTest.php`
Expected: FAIL on the primitives that still use `gray-*`/`indigo-*`.

- [ ] **Step 3: Restyle the primitives**

`PrimaryButton.vue` template:

```vue
<template>
    <button
        class="inline-flex items-center justify-center gap-2 rounded-control bg-gold px-4 py-2 text-sm font-semibold text-ink transition duration-150 ease-qayema hover:bg-gold-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-on disabled:pointer-events-none disabled:opacity-50"
    >
        <slot />
    </button>
</template>
```

`SecondaryButton.vue`: keep the `<script setup>` (the `type` prop); replace the `<button>` class with:
`inline-flex items-center justify-center gap-2 rounded-control border border-line-strong bg-transparent px-4 py-2 text-sm font-semibold text-fg transition duration-150 ease-qayema hover:bg-hover-wash focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-on disabled:pointer-events-none disabled:opacity-50`

`DangerButton.vue`: class:
`inline-flex items-center justify-center gap-2 rounded-control bg-danger px-4 py-2 text-sm font-semibold text-paper transition duration-150 ease-qayema hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-status-danger disabled:pointer-events-none disabled:opacity-50`

`TextInput.vue` `<input>` class:
`rounded-field border border-line bg-field px-3 py-2 text-sm text-fg shadow-none transition duration-150 ease-qayema placeholder:text-placeholder focus:border-gold-on focus:outline-hidden focus:ring-2 focus:ring-ring-accent disabled:bg-disabled-wash`

`InputLabel.vue` label class: `block text-sm font-medium text-muted`

`InputError.vue` paragraph class: `text-sm text-status-danger`

`Checkbox.vue` class: `rounded border-line-strong bg-field text-accent-fill shadow-none focus:ring-2 focus:ring-ring-accent focus:ring-offset-0`

`Modal.vue`: change the scrim div class to `absolute inset-0 bg-black/60` (replacing `bg-gray-500 opacity-75 dark:bg-gray-900`), and the panel class to
`mb-6 transform overflow-hidden rounded-2xl border border-line bg-surface shadow-pop transition-all sm:mx-auto sm:w-full`.

`Dropdown.vue`: default `contentClasses` becomes `'py-1 bg-surface'`; the outer popup div class becomes `absolute z-50 mt-2 rounded-control` (drop `shadow-lg`); the inner div class becomes `rounded-control border border-line shadow-pop` (drop `ring-1 ring-black ring-opacity-5`) keeping `:class="contentClasses"`.

`DropdownLink.vue` `<Link>` class:
`block w-full px-4 py-2 text-start text-sm text-muted transition duration-150 ease-qayema hover:bg-hover-wash hover:text-fg focus:bg-hover-wash focus:outline-hidden`

`NavLink.vue` computed classes:

```js
const classes = computed(() =>
    props.active
        ? 'inline-flex items-center border-b-2 border-gold-on px-1 pt-1 text-sm font-medium text-fg transition duration-150 ease-qayema focus:outline-hidden'
        : 'inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-muted transition duration-150 ease-qayema hover:border-line-strong hover:text-fg focus:outline-hidden',
);
```

`ResponsiveNavLink.vue`: Read it, keep its script, map classes by the Migration rules (active: `border-s-4 border-gold-on bg-accent-wash text-fg`; inactive: `border-s-4 border-transparent text-muted hover:bg-hover-wash hover:text-fg`; `block w-full ps-3 pe-4 py-2 text-start text-base font-medium`).

`LanguageToggle.vue`: replace the inline `<svg>` with the lucide `Languages` icon and restyle:

```vue
<script setup>
import { Languages } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { setLocale } from '@/i18n';

const { locale } = useI18n();

function toggleLanguage() {
    const newLocale = locale.value === 'en' ? 'ar' : 'en';
    setLocale(newLocale);
}
</script>

<template>
    <button
        @click="toggleLanguage"
        type="button"
        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-control px-3 text-sm font-medium text-muted transition duration-150 ease-qayema hover:bg-hover-wash hover:text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-on"
    >
        <Languages class="h-4 w-4" />
        <span class="font-semibold">{{ locale === 'en' ? 'عربي' : 'EN' }}</span>
    </button>
</template>
```

- [ ] **Step 4: Run guard and build; expect PASS / success**

Run: `php artisan test --compact tests/Unit/MigratedFilesUseTokensTest.php` then `bun run build`.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/MigratedFilesUseTokensTest.php resources/js/Components
git commit -m "Restyle primitives on theme tokens and add token guard test

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 4: New shared components

**Files:**
- Create: `resources/js/Components/{PageHeader,Card,StatCard,StatusBadge,EmptyState}.vue`
- Modify: `tests/Unit/MigratedFilesUseTokensTest.php` (add the five files to `MIGRATED`)

**Interfaces:**
- Produces:
  - `<PageHeader title="" description="" :count="">` with slot `actions`.
  - `<Card title="" description="" :padded="true">` with slots `default`, `actions`.
  - `<StatCard label="" value="" hint="">` (slot `hint` overrides the prop).
  - `<StatusBadge status="danger|warn|success|info|neutral">` default slot is the label.
  - `<EmptyState :icon="Component" title="" description="">` with default slot for an action.

- [ ] **Step 1: Add the five paths to `MIGRATED`; run the guard; expect FAIL ("does not exist")**

Run: `php artisan test --compact tests/Unit/MigratedFilesUseTokensTest.php`

- [ ] **Step 2: Create the components**

`PageHeader.vue`:

```vue
<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
    count: { type: [String, Number], default: null },
});
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <h1 class="truncate font-display text-3xl font-normal text-fg">{{ title }}</h1>
                <span
                    v-if="count !== null"
                    class="rounded-full border border-line bg-hover-wash px-2.5 py-0.5 text-xs text-muted"
                >
                    {{ count }}
                </span>
            </div>
            <p v-if="description" class="mt-1 text-sm text-muted">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
```

`Card.vue`:

```vue
<script setup>
defineProps({
    title: { type: String, default: null },
    description: { type: String, default: null },
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <section class="rounded-2xl border border-line bg-surface">
        <header
            v-if="title || $slots.actions"
            class="flex items-start justify-between gap-4 border-b border-line-2 px-5 py-4"
        >
            <div class="min-w-0">
                <h2 v-if="title" class="font-display text-xs font-normal uppercase tracking-widest text-muted">
                    {{ title }}
                </h2>
                <p v-if="description" class="mt-1 text-sm text-muted">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </header>
        <div :class="padded ? 'p-5' : ''">
            <slot />
        </div>
    </section>
</template>
```

`StatCard.vue`:

```vue
<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: null },
});
</script>

<template>
    <div class="rounded-2xl border border-line bg-surface p-5">
        <p class="text-xs text-muted">{{ label }}</p>
        <p class="mt-2 truncate font-display text-3xl font-normal text-fg">{{ value }}</p>
        <p v-if="hint || $slots.hint" class="mt-1 text-xs text-faint">
            <slot name="hint">{{ hint }}</slot>
        </p>
    </div>
</template>
```

`StatusBadge.vue`:

```vue
<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: 'neutral',
        validator: (value) => ['danger', 'warn', 'success', 'info', 'neutral'].includes(value),
    },
});

const classes = computed(
    () =>
        ({
            danger: 'border-status-danger-border bg-status-danger-wash text-status-danger',
            warn: 'border-status-warn-border bg-status-warn-wash text-status-warn',
            success: 'border-status-success-border bg-status-success-wash text-status-success',
            info: 'border-status-info-border bg-status-info-wash text-status-info',
            neutral: 'border-line bg-hover-wash text-muted',
        })[props.status],
);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
        :class="classes"
    >
        <slot />
    </span>
</template>
```

`EmptyState.vue`:

```vue
<script setup>
defineProps({
    icon: { type: [Object, Function], default: null },
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
        <div
            v-if="icon"
            class="flex h-12 w-12 items-center justify-center rounded-full border border-line bg-hover-wash text-muted"
        >
            <component :is="icon" class="h-5 w-5" />
        </div>
        <p class="font-display text-xl font-normal text-fg">{{ title }}</p>
        <p v-if="description" class="max-w-sm text-sm text-muted">{{ description }}</p>
        <div v-if="$slots.default" class="mt-2"><slot /></div>
    </div>
</template>
```

- [ ] **Step 3: Guard and build; expect PASS / success**

Run: `php artisan test --compact tests/Unit/MigratedFilesUseTokensTest.php` then `bun run build`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components tests/Unit/MigratedFilesUseTokensTest.php
git commit -m "Add PageHeader, Card, StatCard, StatusBadge and EmptyState

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Sidebar shell

**Files:**
- Modify: `resources/js/Layouts/SidebarLayout.vue`, `tests/Unit/MigratedFilesUseTokensTest.php` (add `Layouts/SidebarLayout.vue`)

**Interfaces:**
- Consumes: `LanguageToggle`, `ThemeToggle`, `Dropdown`, `DropdownLink`, i18n keys `nav.collapse`, `nav.expand`, `nav.openMenu`.
- Produces: unchanged public API: default slot (page content) and `header` slot (topbar title; falls back to `t('nav.dashboard')`). Navigation items and permission filtering are unchanged.

- [ ] **Step 1: Add `Layouts/SidebarLayout.vue` to `MIGRATED`; run guard; expect FAIL**

- [ ] **Step 2: Replace the icon plumbing in the script**

Replace the import block at the top of `<script setup>` with:

```js
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    ArrowLeftRight,
    Boxes,
    Building,
    Building2,
    ChartColumn,
    ChartLine,
    ChevronDown,
    ChevronsLeft,
    ChevronsRight,
    CircleDollarSign,
    LayoutDashboard,
    ListOrdered,
    Menu,
    Monitor,
    MapPin,
    Package,
    Receipt,
    Scale,
    Settings,
    ShoppingCart,
    Tag,
    Truck,
    Users,
    UsersRound,
    X,
} from 'lucide-vue-next';
import LanguageToggle from '@/Components/LanguageToggle.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
```

Replace `const sidebarOpen = ref(true);` with:

```js
const COLLAPSE_KEY = 'sidebar-collapsed';

function readCollapsed() {
    try {
        return localStorage.getItem(COLLAPSE_KEY) === '1';
    } catch {
        return false;
    }
}

const collapsed = ref(readCollapsed());

function toggleCollapsed() {
    collapsed.value = !collapsed.value;

    try {
        localStorage.setItem(COLLAPSE_KEY, collapsed.value ? '1' : '0');
    } catch {
        // Persisting is optional.
    }
}
```

Truncate the old icon table and template: find the line number with `grep -n "^const getIcon" resources/js/Layouts/SidebarLayout.vue` (call it N) and run `head -n $((N-1)) resources/js/Layouts/SidebarLayout.vue > /tmp/sidebar-head.vue && mv /tmp/sidebar-head.vue resources/js/Layouts/SidebarLayout.vue`. Then append (the file now ends after the `navigation` computed):

```vue
const iconComponents = {
    home: LayoutDashboard,
    terminal: Monitor,
    package: Package,
    tag: Tag,
    inventory: Boxes,
    truck: Truck,
    'shopping-cart': ShoppingCart,
    transfer: ArrowLeftRight,
    chart: ChartColumn,
    receipt: Receipt,
    cog: Settings,
    currency: CircleDollarSign,
    branch: Building,
    location: MapPin,
    company: Building2,
    users: Users,
    scale: Scale,
    'users-group': UsersRound,
    'list-chart': ListOrdered,
    profit: ChartLine,
};

const getIcon = (icon) => iconComponents[icon] || LayoutDashboard;

const linkClasses = (active) => [
    'relative flex items-center gap-3 rounded-control px-3 py-2.5 text-sm font-medium transition-colors duration-150 ease-qayema',
    active
        ? 'bg-accent-wash text-accent before:absolute before:inset-y-2 before:start-0 before:w-0.5 before:rounded-full before:bg-gold-on'
        : 'text-muted hover:bg-hover-wash hover:text-fg',
    collapsed.value ? 'lg:justify-center lg:px-0' : '',
];
</script>

<template>
    <div class="min-h-screen bg-bg text-fg">
        <button
            type="button"
            class="fixed start-3 top-3 z-50 rounded-control border border-line bg-surface p-2 text-fg lg:hidden"
            :aria-label="t('nav.openMenu')"
            @click="mobileMenuOpen = !mobileMenuOpen"
        >
            <component :is="mobileMenuOpen ? X : Menu" class="h-5 w-5" />
        </button>

        <aside
            :class="[
                'fixed inset-y-0 start-0 z-40 flex w-64 flex-col border-e border-line bg-bg transition-[width,transform] duration-300 ease-qayema',
                collapsed ? 'lg:w-[72px]' : 'lg:w-64',
                mobileMenuOpen
                    ? 'translate-x-0'
                    : 'ltr:-translate-x-full rtl:translate-x-full lg:translate-x-0',
            ]"
        >
            <div class="flex h-16 shrink-0 items-center justify-center border-b border-line px-4">
                <span
                    class="truncate font-display text-lg font-normal uppercase tracking-[0.3em] text-accent"
                >
                    {{ collapsed ? 'S' : 'Swipe n Sell' }}
                </span>
            </div>

            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <template v-for="(item, index) in navigation" :key="index">
                    <Link
                        v-if="item.href"
                        :href="item.href"
                        :title="collapsed ? item.name : undefined"
                        :class="linkClasses(item.active)"
                        @click="mobileMenuOpen = false"
                    >
                        <component :is="getIcon(item.icon)" class="h-[18px] w-[18px] shrink-0" />
                        <span :class="collapsed ? 'lg:hidden' : ''" class="truncate">{{ item.name }}</span>
                    </Link>

                    <div v-else-if="item.section" class="pt-4">
                        <p
                            :class="collapsed ? 'lg:hidden' : ''"
                            class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-widest text-faint"
                        >
                            {{ item.section }}
                        </p>
                        <div v-if="collapsed" class="mx-3 mb-2 hidden border-t border-line-2 lg:block"></div>
                        <Link
                            v-for="subItem in item.items"
                            :key="subItem.name"
                            :href="subItem.href"
                            :title="collapsed ? subItem.name : undefined"
                            :class="linkClasses(subItem.active)"
                            @click="mobileMenuOpen = false"
                        >
                            <component :is="getIcon(subItem.icon)" class="h-[18px] w-[18px] shrink-0" />
                            <span :class="collapsed ? 'lg:hidden' : ''" class="truncate">{{ subItem.name }}</span>
                        </Link>
                    </div>
                </template>
            </nav>

            <div class="hidden shrink-0 border-t border-line p-3 lg:block">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-control px-3 py-2.5 text-sm text-muted transition-colors duration-150 ease-qayema hover:bg-hover-wash hover:text-fg"
                    :class="collapsed ? 'justify-center px-0' : ''"
                    :title="collapsed ? t('nav.expand') : t('nav.collapse')"
                    @click="toggleCollapsed"
                >
                    <component
                        :is="collapsed ? ChevronsRight : ChevronsLeft"
                        class="h-[18px] w-[18px] shrink-0 rtl:rotate-180"
                    />
                    <span v-if="!collapsed">{{ t('nav.collapse') }}</span>
                </button>
            </div>
        </aside>

        <div
            v-if="mobileMenuOpen"
            class="fixed inset-0 z-30 bg-black/60 lg:hidden"
            @click="mobileMenuOpen = false"
        ></div>

        <div
            class="transition-[padding] duration-300 ease-qayema"
            :class="collapsed ? 'lg:ps-[72px]' : 'lg:ps-64'"
        >
            <header class="sticky top-0 z-20 border-b border-line bg-bg/90 backdrop-blur">
                <div class="flex h-16 items-center justify-between gap-4 ps-14 pe-4 sm:pe-6 lg:ps-8 lg:pe-8">
                    <div class="min-w-0 font-display text-xl font-normal text-fg">
                        <slot name="header">
                            <h1 class="truncate">{{ t('nav.dashboard') }}</h1>
                        </slot>
                    </div>

                    <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                        <div
                            v-if="currentBranch"
                            class="hidden max-w-48 items-center gap-2 rounded-full border border-line bg-surface px-3 py-1.5 text-sm text-muted sm:flex"
                        >
                            <Building2 class="h-4 w-4 shrink-0" />
                            <span class="truncate">{{ currentBranch?.name }}</span>
                        </div>

                        <LanguageToggle />
                        <ThemeToggle />

                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="flex items-center gap-2 rounded-full border border-line bg-surface py-1 ps-1 pe-2 text-sm text-muted transition-colors duration-150 ease-qayema hover:bg-hover-wash"
                                >
                                    <span
                                        class="flex h-7 w-7 items-center justify-center rounded-full bg-accent-fill text-xs font-semibold text-ink"
                                    >
                                        {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                                    </span>
                                    <ChevronDown class="h-4 w-4" />
                                </button>
                            </template>

                            <template #content>
                                <div class="border-b border-line-2 px-4 py-2">
                                    <p class="truncate text-sm font-medium text-fg">{{ user?.name }}</p>
                                    <p class="truncate text-xs text-faint">{{ user?.email }}</p>
                                </div>
                                <DropdownLink :href="route('profile.edit')">
                                    {{ t('nav.profile') }}
                                </DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">
                                    {{ t('nav.logout') }}
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
```

Note: `DropdownLink` currently declares only an `href` prop but is used with `method` and `as`; those fall through as attributes to `<Link>`, as before.

- [ ] **Step 3: Guard + build**

Run: `php artisan test --compact tests/Unit/MigratedFilesUseTokensTest.php`; expect PASS. Run `bun run build`; if a lucide name is not exported, resolve it using Batch procedure step 4.

- [ ] **Step 4: Manual checks**

Open `/dashboard` in light, dark, Arabic (RTL), and a narrow viewport. Check: active item shows the gold wash and bar; collapse works and persists across reload; the mobile drawer slides from the start edge in RTL; a user with no permissions (create one via factory/tinker, or temporarily log in as a restricted user) shows an empty nav without errors and no `currentBranch` pill; a very long user name truncates in the dropdown.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Layouts/SidebarLayout.vue tests/Unit/MigratedFilesUseTokensTest.php
git commit -m "Rebuild SidebarLayout with token styling, lucide icons and collapse

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 6: AuthenticatedLayout and GuestLayout

**Files:**
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`, `resources/js/Layouts/GuestLayout.vue`, `tests/Unit/MigratedFilesUseTokensTest.php`

**Interfaces:**
- Consumes: `ThemeToggle`, `LanguageToggle`, `NavLink`, `ResponsiveNavLink`, `Dropdown`, `DropdownLink`, `ApplicationLogo`.
- Produces: same slots as today (`header`, default).

- [ ] **Step 1: Add both layouts to `MIGRATED`; run guard; expect FAIL**

- [ ] **Step 2: Migrate `AuthenticatedLayout.vue`**

Read it. Keep script logic (state, routes). Apply the Migration rules to the template: page background `bg-bg`; nav bar `border-b border-line bg-surface`; logo wrapper `text-accent`, `ApplicationLogo` gets `class="block h-9 w-auto fill-current text-accent"`; replace inline SVGs (hamburger, chevron) with lucide `Menu`, `X`, `ChevronDown`; header band `border-b border-line bg-bg` with slot wrapped in `font-display text-2xl font-normal text-fg`; add `<LanguageToggle />` and `<ThemeToggle />` next to the user dropdown.

- [ ] **Step 3: Replace `GuestLayout.vue` template (keep its imports; add the two toggles and `Link`)**

```vue
<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import LanguageToggle from '@/Components/LanguageToggle.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center bg-bg px-4 py-10">
        <div class="absolute end-4 top-4 flex items-center gap-1">
            <LanguageToggle />
            <ThemeToggle />
        </div>

        <Link href="/" class="mb-6 flex flex-col items-center gap-3">
            <ApplicationLogo class="h-14 w-14 fill-current text-accent" />
            <span class="font-display text-xl font-normal uppercase tracking-[0.3em] text-accent">Swipe n Sell</span>
        </Link>

        <div class="w-full max-w-md rounded-2xl border border-line bg-surface px-6 py-6 shadow-lift">
            <slot />
        </div>
    </div>
</template>
```

- [ ] **Step 4: Guard, build, test, manual check**

Run guard (PASS), `bun run build`, `php artisan test --compact` (all PASS). Open `/profile` (AuthenticatedLayout) and `/forgot-password` (GuestLayout) in both themes and Arabic.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Layouts tests/Unit/MigratedFilesUseTokensTest.php
git commit -m "Restyle AuthenticatedLayout and GuestLayout

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Dashboard

**Files:** Modify `resources/js/Pages/Dashboard.vue`; add it to `MIGRATED`.

Apply the Batch procedure. Page-specific: build the top stat row from `StatCard` (serif numbers via the component), wrap lists and charts in `Card` with `title`, show status pills with `StatusBadge`, and use `EmptyState` when a list is empty. Keep all data bindings.

- [ ] Steps 1-7 of the Batch procedure. Commit message: `Restyle Dashboard`.

### Task 8: Catalog pages

**Files:** `resources/js/Pages/Products/{Index,Create,Edit}.vue`, `Categories/{Index,Create,Edit}.vue`, `Units/Index.vue`.

Page-specific: list pages use `PageHeader` with `:count` (total rows when the page already has it) and an `#actions` slot holding the existing "add" link styled as the gold primary action; tables/lists sit in `Card :padded="false"` with `divide-y divide-line`; availability/active flags use `StatusBadge`; forms use `Card` sections with `InputLabel`/`TextInput`/`InputError`.

- [ ] Batch procedure. Commit message: `Restyle catalog pages`.

### Task 9: Inventory and purchasing pages

**Files:** `Inventory/{Index,Adjust,Movements,Transfer}.vue`, `Suppliers/{Index,Create,Edit}.vue`, `Purchases/{Index,Create}.vue`.

Page-specific: low-stock and movement-type pills use `StatusBadge` (low stock `warn`, out `danger`, in `success`, transfer `info`); `Inventory/Index.vue` already imports `ArrowLeftRight` from lucide, keep it; `Purchases/Create.vue` (434 lines) and `Inventory/Transfer.vue` (345 lines) have line-item tables, keep the row logic and only change classes.

- [ ] Batch procedure. Commit message: `Restyle inventory and purchasing pages`.

### Task 10: Sales and people pages

**Files:** `Orders/{Index,Show}.vue`, `Customers/{Index,Create,Edit,Show}.vue`, `Users/{Index,Create,Edit}.vue`.

Page-specific: order status pills use `StatusBadge` (completed `success`, pending `warn`, cancelled/refunded `danger`, other `neutral`); currency totals and order totals use `font-display`; role chips on Users use `StatusBadge status="info"`.

- [ ] Batch procedure. Commit message: `Restyle sales and people pages`.

### Task 11: Organisation and settings pages

**Files:** `Branches/{Index,Create,Edit}.vue`, `Locations/{Index,Create,Edit}.vue`, `ExchangeRates/Index.vue`, `Settings/Index.vue`.

- [ ] Batch procedure. Commit message: `Restyle branches, locations, exchange rates and settings`.

### Task 12: Reports and admin pages

**Files:** `Reports/{Items,Profit,Sales}.vue`, `Admin/Companies/{Index,Create,Edit,Show}.vue`.

Page-specific: report summary figures use `StatCard`; tables in `Card :padded="false"`; profit positive/negative text uses `text-status-success` / `text-status-danger`.

- [ ] Batch procedure. Commit message: `Restyle reports and admin pages`.

### Task 13: Profile, Welcome and Auth pages

**Files:** `Profile/Edit.vue`, `Profile/Partials/{DeleteUserForm,UpdatePasswordForm,UpdateProfileInformationForm}.vue`, `Welcome.vue`, `Auth/{ConfirmPassword,ForgotPassword,Login,Register,ResetPassword,VerifyEmail}.vue`.

Page-specific: profile partials are `Card` sections with `title`; `DeleteUserForm` danger button is `DangerButton`; `Login.vue` has its own layout, so give it the same centred card as `GuestLayout` and add `LanguageToggle` plus `ThemeToggle`; `Welcome.vue` (386 lines) is a marketing page: keep structure, apply tokens, use `font-display` for headline.

- [ ] Batch procedure. Commit message: `Restyle profile, welcome and auth pages`.

### Task 14: POS page and components

**Files:** `Pages/POS/Index.vue`, `Components/POS/{CartItem,CartPanel,CategoryTabs,DiscountInput,OfflineIndicator,ProductCard,ProductGrid,ReceiptModal,SearchBar}.vue`.

Page-specific:
- Product cards use `border border-line bg-surface rounded-2xl hover:shadow-lift`; unavailable/out-of-stock overlay uses `bg-overlay-pill text-paper`; category tabs follow the dishes-tab look (`border border-line rounded-control`, active `bg-accent-wash border-accent-border text-accent`).
- `OfflineIndicator` uses `StatusBadge`-style `warn`/`success` tokens.
- The printable receipt area in `ReceiptModal.vue` must stay white-on-dark-text in both themes: keep its literal colours and mark each such line with a trailing `<!-- allow-colour: printable receipt -->` (or `/* allow-colour */` inside a `<style>` block). Everything else in the modal (buttons, header, backdrop) uses tokens.
- Touch targets stay at least 40px high; do not shrink existing padding on cart quantity buttons.
- Keep every composable call (`useCart`, `useBarcode`, `useOfflineQueue`, `useCurrency`) untouched.

- [ ] Batch procedure, with step 6 extended: ring up a sale in light and dark, open the receipt, and use the browser print preview in dark theme to confirm the receipt prints dark text on white.

Commit message: `Restyle POS`.

### Task 15: Whole-tree guard, cleanup and final verification

**Files:** Modify `tests/Unit/MigratedFilesUseTokensTest.php`; possibly any remaining `.vue` file.

- [ ] **Step 1: Make the guard cover everything**

Replace the `MIGRATED` constant and its dataset with a glob of all Vue files:

```php
function allVueFiles(): array
{
    $root = realpath(__DIR__.'/../../resources/js');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $files = [];

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'vue') {
            $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }

    sort($files);

    return $files;
}
```

and use `->with(allVueFiles())` instead of `->with(MIGRATED)` (remove the `MIGRATED` constant).

- [ ] **Step 2: Run it; migrate anything still failing**

Run: `php artisan test --compact tests/Unit/MigratedFilesUseTokensTest.php`
Expected: FAIL only for files nobody migrated (for example `ApplicationLogo.vue` if it contains a hex fill, or stray components). Apply the Migration rules; for a file that legitimately needs a literal colour, mark the line `allow-colour` and say why in the commit message.

- [ ] **Step 3: Full verification**

Run, expecting success from each:
- `php artisan test --compact`
- `bun run build`
- `vendor/bin/pint --dirty`

Manual: walk Dashboard, one list, one form, Reports, Settings, POS and the receipt in light, dark and Arabic RTL; test the theme toggle persists across reload and that blocking storage (DevTools: block site data) still renders light without console errors. Record anything not checked.

- [ ] **Step 4: Commit**

```bash
git add -A resources tests
git commit -m "Guard all Vue files against hard-coded colours and finish redesign cleanup

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

## Self-review notes

- **Spec coverage:** Phase 0 and 1 are Tasks 1-2; Phase 2 is Tasks 5-6; Phase 3 is Tasks 3-4; Phase 4's ordering is Tasks 7-14 (Dashboard, lists, forms grouped by domain rather than by type, which keeps each batch's files coherent; Reports, Settings, Profile, Welcome, Auth, then POS last); Phase 5 and the contrast and smoke tests are Tasks 1, 2 and 15.
- **Deviations from the spec:** pages are batched by domain instead of by type (list, form, report), and the token utility for `--text` is `text-fg`.
- **Interface names:** `useTheme`, `ThemeToggle`, `PageHeader`, `Card`, `StatCard`, `StatusBadge`, `EmptyState` and the `MIGRATED` constant are defined in Tasks 2-4 and used unchanged afterwards.
