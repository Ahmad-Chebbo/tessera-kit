import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import CartPanel from '@modules/Pos/Resources/js/Components/POS/CartPanel.vue';
import { useCart } from '@modules/Pos/Resources/js/Composables/useCart';
import { i18n } from '@/Components/__tests__/helpers';

vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: { currency: 'USD' } }) }));
vi.mock('axios', () => ({ default: { post: vi.fn() } }));

const customers = [
    { id: 1, name: 'Rana Haddad', phone: '0123' },
    { id: 2, name: 'Omar Saleh', phone: '0456' },
];

const mountPanel = () =>
    mount(CartPanel, {
        props: { currency: 'USD', customers },
        attachTo: document.body,
        global: { plugins: [i18n], stubs: { teleport: true } },
    });

const chip = (wrapper, name) => wrapper.find(`[data-chip="${name}"]`);

describe('CartPanel footer', () => {
    let cart;

    beforeEach(() => {
        document.body.innerHTML = '';
        cart = useCart();
        cart.clearCart();
        cart.addItem({ id: 1, name: 'Cola', price: 100, stock: 50, currency: 'USD' });
    });

    it('shows nothing of the footer for an empty cart', () => {
        cart.clearCart();

        expect(mountPanel().find('[data-chip="customer"]').exists()).toBe(false);
    });

    it('keeps customer, discount and coupon as compact chips instead of open forms', () => {
        const wrapper = mountPanel();

        expect(['customer', 'discount', 'coupon'].every((name) => chip(wrapper, name).exists())).toBe(true);
        expect(wrapper.find('input[placeholder="Search customer..."]').exists()).toBe(false);
        expect(wrapper.find('input[placeholder="Coupon code"]').exists()).toBe(false);
    });

    it('opens one panel at a time and closes it when the chip is pressed again', async () => {
        const wrapper = mountPanel();

        await chip(wrapper, 'customer').trigger('click');
        expect(wrapper.find('input[placeholder="Search customer..."]').exists()).toBe(true);

        await chip(wrapper, 'coupon').trigger('click');
        expect(wrapper.find('input[placeholder="Search customer..."]').exists()).toBe(false);
        expect(wrapper.find('input[placeholder="Coupon code"]').exists()).toBe(true);

        await chip(wrapper, 'coupon').trigger('click');
        expect(wrapper.find('input[placeholder="Coupon code"]').exists()).toBe(false);
    });

    it('closes the open panel when clicking elsewhere', async () => {
        const wrapper = mountPanel();

        await chip(wrapper, 'coupon').trigger('click');
        document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('input[placeholder="Coupon code"]').exists()).toBe(false);
    });

    it('picks a customer from the list, shows the name on the chip and closes the panel', async () => {
        const wrapper = mountPanel();

        await chip(wrapper, 'customer').trigger('click');
        await wrapper.find('input[placeholder="Search customer..."]').setValue('omar');

        const options = wrapper.findAll('button').filter((button) => button.text().includes('Omar Saleh') || button.text().includes('Rana'));
        expect(options).toHaveLength(1);

        await options[0].trigger('click');

        expect(cart.selectedCustomerId.value).toBe(2);
        expect(chip(wrapper, 'customer').text()).toContain('Omar Saleh');
        expect(wrapper.find('input[placeholder="Search customer..."]').exists()).toBe(false);
    });

    it('lets a cash sale go through without a customer', async () => {
        const wrapper = mountPanel();

        await wrapper.find('input[aria-label="Cash Received"]').setValue('100');
        await wrapper.findAll('button').find((button) => button.text() === 'Complete Sale').trigger('click');

        expect(wrapper.emitted('checkout')[0][0]).toMatchObject({ customerId: null, paymentType: 'cash', cashReceived: 100 });
    });

    it('asks for a customer on credit sales and blocks the sale until one is chosen', async () => {
        const wrapper = mountPanel();

        await wrapper.findAll('button').find((button) => button.text() === 'Debt').trigger('click');

        expect(chip(wrapper, 'customer').text()).toContain('Customer required for debt orders');
        expect(wrapper.findAll('button').find((button) => button.text() === 'Complete Sale').attributes('disabled')).toBeDefined();

        cart.selectedCustomerId.value = 1;
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('button').find((button) => button.text() === 'Complete Sale').attributes('disabled')).toBeUndefined();
    });

    it('applies a discount from its panel and shows it on the chip and in the totals', async () => {
        const wrapper = mountPanel();

        await chip(wrapper, 'discount').trigger('click');
        await wrapper.find('input[placeholder="10"]').setValue('10');
        await wrapper.findAll('button').find((button) => button.text() === 'Confirm').trigger('click');

        expect(chip(wrapper, 'discount').text()).toContain('10%');
        expect(wrapper.text()).toContain('Subtotal');
        expect(wrapper.text()).toContain('$90.00');
        expect(wrapper.find('input[placeholder="10"]').exists()).toBe(false);
    });

    it('only lists subtotal and adjustment lines when there is an adjustment', () => {
        const plain = mountPanel();
        expect(plain.text()).not.toContain('Subtotal');

        cart.setRulesPreview({ total: 20, coupon_status: 'none', lines: [] });
        const withPromo = mountPanel();

        expect(withPromo.text()).toContain('Subtotal');
        expect(withPromo.text()).toContain('Promotions');
    });

    it('marks the coupon chip according to the coupon result', async () => {
        const wrapper = mountPanel();
        cart.couponCode.value = 'SPRING';

        cart.setRulesPreview({ total: 5, coupon_status: 'applied', lines: [] });
        await wrapper.vm.$nextTick();
        expect(chip(wrapper, 'coupon').classes().join(' ')).toContain('status-success');
        expect(chip(wrapper, 'coupon').text()).toBe('SPRING');

        cart.setRulesPreview({ total: 0, coupon_status: 'unknown', lines: [] });
        await wrapper.vm.$nextTick();
        expect(chip(wrapper, 'coupon').classes().join(' ')).toContain('status-danger');
    });

    it('offers at most three quick cash amounts', () => {
        const wrapper = mountPanel();

        const quick = wrapper.findAll('button').filter((button) => /^\$\d/.test(button.text()) && !button.attributes('data-chip'));
        expect(quick.length).toBeLessThanOrEqual(3);
        expect(quick.length).toBeGreaterThan(0);
    });

    it('reminds the cashier of free items the customer earned but has not added', () => {
        cart.setRulesPreview({
            total: 0,
            coupon_status: 'none',
            lines: [],
            suggestions: [{ rule_id: 1, rule_name: 'Burger deal', quantity: 2, products: [{ id: 5, name: 'Cola' }, { id: 6, name: 'Juice' }] }],
        });

        const hint = mountPanel().find('[data-suggestion]');

        expect(hint.text()).toBe('Add 2 × Cola / Juice to the cart: free with "Burger deal"');
    });

    it('shows no reminder when nothing is pending', () => {
        cart.setRulesPreview({ total: 0, coupon_status: 'none', lines: [], suggestions: [] });

        expect(mountPanel().find('[data-suggestion]').exists()).toBe(false);
    });
});
