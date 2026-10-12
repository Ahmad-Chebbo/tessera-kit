# Tessera UI

The interface kit of [Tessera](https://github.com/Tessera-kit/tessera-kit): Vue 3 components, composables, chart helpers and the theme, for Inertia and Vite projects. It does not need the Tessera engine or core, so it also suits a plain Laravel + Inertia project such as a CMS.

It ships source files (no build step); Vite compiles them with your app.

## Install

```bash
bun add github:Tessera-kit/tessera-ui#v1.0.2
```

Peer dependencies: `vue`, `vue-i18n`, `@inertiajs/vue3`, `lucide-vue-next`, `chart.js`, `vue-chartjs`, `tailwindcss` 4 and `@tailwindcss/forms`.

## Set up

In `resources/css/app.css` (Tailwind 4):

```css
@import "tailwindcss";
@import "@tessera/tessera-ui/theme.css";
@plugin "@tailwindcss/forms";
```

`theme.css` holds the design tokens (light and dark, print) and tells Tailwind to scan the package, so no `@source` line is needed for it.

In `resources/js/app.js`, create the i18n instance with the UI texts and your own:

```js
import { createTesseraI18n, uiMessages } from '@tessera/tessera-ui/i18n';
import en from './locales/en.json';

const i18n = createTesseraI18n({ en: { ...uiMessages.en, ...en } });
```

`setLocale('ar')` switches the language, keeps it in `localStorage` and flips the page direction (right-to-left for Arabic).

## What is inside

```js
import Card from '@tessera/tessera-ui/Components/Card.vue';
import { useToast } from '@tessera/tessera-ui/Composables/useToast';
```

- **Components**: `Card`, `StatCard`, `ChartCard`, `DataTable`, `CardGrid`, `GridCard`, `Pagination`, `FilterTabs`, `ViewToggle`, `Select`, `DatePicker` (with optional time), `FileUpload`, `TextInput`, `InputLabel`, `InputError`, `Checkbox`, `PrimaryButton`, `SecondaryButton`, `DangerButton`, `IconButton`, `Modal`, `ConfirmDialog`, `Dropdown`, `DropdownLink`, `NavLink`, `ResponsiveNavLink`, `PageHeader`, `EmptyState`, `StatusBadge`, `ScrollArea`, `PeriodPresets`, `ToastContainer`, `ThemeToggle`, `LanguageToggle`, and the charts `BarChart`, `LineChart`, `DoughnutChart`, `TrendChart`.
- **Composables**: `useToast`, `useConfirm`, `useTheme`, `useViewMode` (table or grid, remembered), `useDuration`, `useChartTheme`.
- **Helpers**: `utils/date`, `charts/*` (options, theme, labels), and `registerInertiaToasts` which turns Inertia flash messages into toasts.

Mount `ToastContainer` and `ConfirmDialog` once in your layout. Use the tokens (for example `bg-surface`, `text-muted`) rather than raw colours.

## Tests

The package's tests live in `src/**/__tests__` and run with Vitest from the monorepo (`bun run test`).

## Part of Tessera

This repository is a read-only copy of a folder in the [tessera-kit](https://github.com/Tessera-kit/tessera-kit) monorepo. Open issues and pull requests there, not here.

## License

All rights reserved until a license is added to the monorepo.
