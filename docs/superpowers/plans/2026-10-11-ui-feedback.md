# Toasts, Confirm Dialog and DataTable Plan

Design approved in chat on 2026-10-11 (serves as the spec). Decisions: toasts appear top at the end edge; DataTable conversion covers every table in the app.

1. Backend: share `flash` (success, error, status) via `HandleInertiaRequests`; Pest feature test.
2. Toast: `useToast`, `ToastContainer`, Inertia bridge (flash props, validation/network errors, POS axios errors); mounted in every layout; Vitest tests.
3. Confirm: `useConfirm`, `ConfirmDialog` built on `Modal.vue`; replace all 13 native `confirm()` calls; Vitest tests.
4. Tables/grids: `DataTable` (columns + cell slots + empty state + paginator) and `CardGrid`; convert all 22 tables and the 13 grid wrappers; Vitest tests.
5. Verify: `bun run test`, `bun run build`, `php artisan test --compact` (baseline 17 pre-existing failures), guard tests; one fresh review.
