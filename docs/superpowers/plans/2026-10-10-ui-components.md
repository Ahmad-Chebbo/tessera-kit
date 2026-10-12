# UI Components Follow-up Plan

Design approved in chat on 2026-10-10 (serves as the spec). Scope: Select dropdown, FileUpload, IconButton table actions, table/grid view toggle on every list page, FilterTabs, slim sidebar scrollbar with fade indicators. Vitest + Vue Test Utils added for component tests (`bun run test`).

1. Components with tests: Select, FileUpload, IconButton, ViewToggle + useViewMode, FilterTabs, GridCard, ScrollArea, `scroll-thin` utility.
2. Swap every native `<select>` for `Select` (slot-compatible, v-model unchanged).
3. Use `FileUpload` for product image (create/edit) and settings logo.
4. Replace text links in table action cells with `IconButton` (Pencil / Trash2 / Eye).
5. Add table/grid toggle (`ViewToggle`, `GridCard`) to each list page; convert existing status/category filters to `FilterTabs` using the same query parameters.
6. Verify: `bun run test`, `bun run build`, `php artisan test --compact` (baseline 17 pre-existing failures), guard tests.
