import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { i18n } from '@/Components/__tests__/helpers';

const get = vi.fn();
vi.mock('@inertiajs/vue3', () => ({ router: { get: (...args) => get(...args) } }));

import ExpenseFilters from '@modules/Expenses/Resources/js/Pages/Expenses/Partials/ExpenseFilters.vue';

const presets = [
    { key: 'today', from: '2026-10-14', to: '2026-10-14' },
    { key: 'this_month', from: '2026-10-01', to: '2026-10-14' },
];

const mountFilters = (props = {}) =>
    mount(ExpenseFilters, {
        props: { routeName: 'expenses.index', filters: { date_from: '2026-10-01', date_to: '2026-10-14' }, presets, ...props },
        global: { plugins: [i18n], stubs: { DatePicker: true, Select: true } },
    });

describe('ExpenseFilters', () => {
    beforeEach(() => {
        get.mockReset();
        globalThis.route = (name) => `/${name}`;
    });

    it('highlights the preset that matches the current period', () => {
        const wrapper = mountFilters();

        expect(wrapper.findAll('[role="tab"]').map((tab) => tab.attributes('aria-selected'))).toEqual(['false', 'true']);
    });

    it('reloads with the dates of the chosen preset', async () => {
        const wrapper = mountFilters();

        await wrapper.findAll('[role="tab"]')[0].trigger('click');

        expect(get).toHaveBeenCalledWith('/expenses.index', expect.objectContaining({ date_from: '2026-10-14', date_to: '2026-10-14' }), expect.any(Object));
    });

    it('only shows the search box when asked and debounces typing', async () => {
        vi.useFakeTimers();
        expect(mountFilters().find('input[type="text"]').exists()).toBe(false);

        const wrapper = mountFilters({ searchable: true });
        await wrapper.find('input[type="text"]').setValue('rent');
        expect(get).not.toHaveBeenCalled();

        vi.advanceTimersByTime(350);
        expect(get).toHaveBeenCalledWith('/expenses.index', expect.objectContaining({ search: 'rent' }), expect.any(Object));
        vi.useRealTimers();
    });

    it('leaves out filters that are empty', async () => {
        const wrapper = mountFilters();

        await wrapper.findAll('[role="tab"]')[0].trigger('click');

        const params = get.mock.calls[0][1];
        expect(params.category_id).toBeUndefined();
        expect(params.search).toBeUndefined();
    });
});
