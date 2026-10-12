import { describe, expect, it, vi } from 'vitest';

const page = { props: { enabledModules: ['sales', 'catalog'] } };

vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

const { useModules } = await import('../useModules');

describe('useModules', () => {
    it('knows which modules are enabled', () => {
        expect(useModules().has('sales')).toBe(true);
        expect(useModules().has('expenses')).toBe(false);
    });

    it('treats a page without the list as having none', () => {
        page.props = {};

        expect(useModules().has('sales')).toBe(false);
    });
});
