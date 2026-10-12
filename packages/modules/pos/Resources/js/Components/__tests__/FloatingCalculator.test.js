import { beforeEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import FloatingCalculator from '@modules/Pos/Resources/js/Components/POS/FloatingCalculator.vue';
import { i18n } from '@/Components/__tests__/helpers';

const mountCalculator = (props = {}) =>
    mount(FloatingCalculator, {
        props: { open: true, total: 80, subtotal: 100, taxRate: 10, currency: 'USD', ...props },
        global: { plugins: [i18n] },
    });

const tap = async (wrapper, ...keys) => {
    for (const key of keys) {
        await wrapper.find(`[data-key="${key}"]`).trigger('click');
    }
};

describe('FloatingCalculator', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('renders nothing while closed', () => {
        expect(mountCalculator({ open: false }).find('[role="dialog"]').exists()).toBe(false);
    });

    it('builds an expression from the keypad and shows a live result', async () => {
        const wrapper = mountCalculator();

        await tap(wrapper, '1', '2', '+', '8');

        expect(wrapper.find('[data-testid="expression"]').text()).toBe('12+8');
        expect(wrapper.find('[data-testid="result"]').text()).toBe('20');
    });

    it('evaluates on equals and starts a new number after it', async () => {
        const wrapper = mountCalculator();

        await tap(wrapper, '9', '×', '3', '=');
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('27');

        await tap(wrapper, '5');
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('5');
    });

    it('keeps going from the result when an operator follows equals', async () => {
        const wrapper = mountCalculator();

        await tap(wrapper, '2', '0', '=', '+', '5');

        expect(wrapper.find('[data-testid="result"]').text()).toBe('25');
    });

    it('supports percent, backspace, clear and replacing a repeated operator', async () => {
        const wrapper = mountCalculator();

        await tap(wrapper, '2', '0', '0', '+', '1', '0', '%');
        expect(wrapper.find('[data-testid="result"]').text()).toBe('220');

        await tap(wrapper, '⌫', '⌫', '⌫');
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('200+');

        await tap(wrapper, '−');
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('200−');

        await tap(wrapper, 'C');
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('0');
    });

    it('does not start with a multiplication sign but allows a minus', async () => {
        const wrapper = mountCalculator();

        await tap(wrapper, '×');
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('0');

        await tap(wrapper, '−', '5');
        expect(wrapper.find('[data-testid="result"]').text()).toBe('-5');
    });

    it('shows nothing for an incomplete expression and never changes on a bad equals', async () => {
        const wrapper = mountCalculator();

        await tap(wrapper, '5', '+');
        expect(wrapper.find('[data-testid="result"]').text()).toBe('');

        await tap(wrapper, '=');
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('5+');
    });

    it('puts the order total or subtotal on the display', async () => {
        const wrapper = mountCalculator();

        await wrapper.find('[data-action="use-total"]').trigger('click');
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('80');

        await wrapper.find('[data-action="use-subtotal"]').trigger('click');
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('100');
    });

    it('adds and removes tax and takes a discount off the displayed amount', async () => {
        const wrapper = mountCalculator();

        await tap(wrapper, '1', '0', '0');
        await wrapper.find('[data-action="add-tax"]').trigger('click');
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('110');

        await wrapper.find('[data-action="remove-tax"]').trigger('click');
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('100');

        await wrapper.find('input[aria-label="Discount %"]').setValue('25');
        await wrapper.find('[data-action="discount"]').trigger('click');
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('75');
    });

    it('works out change and the remaining balance against the order total', async () => {
        const wrapper = mountCalculator({ total: 80 });
        const paid = wrapper.find('input[aria-label="Customer paid"]');

        await paid.setValue('100');
        expect(wrapper.find('[data-testid="change"]').text()).toBe('$20.00');
        expect(wrapper.find('[data-testid="remaining"]').text()).toBe('$0.00');

        await paid.setValue('30');
        expect(wrapper.find('[data-testid="change"]').text()).toBe('$0.00');
        expect(wrapper.find('[data-testid="remaining"]').text()).toBe('$50.00');
    });

    it('follows the order total as the cart changes', async () => {
        const wrapper = mountCalculator({ total: 80 });
        await wrapper.find('input[aria-label="Customer paid"]').setValue('100');

        await wrapper.setProps({ total: 95 });

        expect(wrapper.find('[data-testid="change"]').text()).toBe('$5.00');
    });

    it('accepts keyboard input and closes with Escape', async () => {
        const wrapper = mountCalculator();
        const dialog = wrapper.find('[role="dialog"]');

        await dialog.trigger('keydown', { key: '6' });
        await dialog.trigger('keydown', { key: '*' });
        await dialog.trigger('keydown', { key: '7' });
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('6×7');

        await dialog.trigger('keydown', { key: 'Enter' });
        expect(wrapper.find('[data-testid="expression"]').text()).toBe('42');

        await dialog.trigger('keydown', { key: 'Escape' });
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('closes from the header button', async () => {
        const wrapper = mountCalculator();

        await wrapper.find('button[aria-label="Close"]').trigger('click');

        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('restores a saved position', async () => {
        localStorage.setItem('pos-calculator-position', JSON.stringify({ x: 40, y: 60 }));

        const wrapper = mountCalculator();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="dialog"]').attributes('style')).toContain('left: 40px');
        expect(wrapper.find('[role="dialog"]').attributes('style')).toContain('top: 60px');
    });
});
