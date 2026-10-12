import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import CartItem from '@modules/Pos/Resources/js/Components/POS/CartItem.vue';
import { i18n } from '@/Components/__tests__/helpers';

vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: { currency: 'USD' } }) }));

const item = {
    id: 1,
    name: 'Rice',
    price: 5,
    quantity: 2,
    image_url: null,
    unit_id: 2,
    unit_abbreviation: 'kg',
    sell_units: [
        { id: 1, abbreviation: 'g' },
        { id: 2, abbreviation: 'kg' },
    ],
};

const mountItem = (overrides = {}) =>
    mount(CartItem, {
        props: { item: { ...item, ...overrides }, currency: 'USD' },
        attachTo: document.body,
        global: { plugins: [i18n], stubs: { teleport: true } },
    });

describe('CartItem unit selector', () => {
    it('shows the current unit', () => {
        const wrapper = mountItem();

        expect(wrapper.get('[role="combobox"]').text()).toBe('kg');
    });

    it('emits the chosen unit id as a number', async () => {
        const wrapper = mountItem();

        await wrapper.get('[role="combobox"]').trigger('click');
        await wrapper.findAll('[role="option"]')[0].trigger('click');

        expect(wrapper.emitted('change-unit')).toEqual([[1]]);
    });

    it('hides the selector when only one sell unit exists', () => {
        const wrapper = mountItem({ sell_units: [{ id: 2, abbreviation: 'kg' }] });

        expect(wrapper.find('[role="combobox"]').exists()).toBe(false);
    });
});
