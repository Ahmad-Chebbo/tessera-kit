import { beforeEach, describe, expect, it } from 'vitest';
import { useCart } from '@modules/Pos/Resources/js/Composables/useCart';

const product = (overrides = {}) => ({ id: 1, name: 'Cola', price: 100, stock: 50, currency: 'USD', ...overrides });

describe('useCart with automatic discounts', () => {
    let cart;

    beforeEach(() => {
        cart = useCart();
        cart.clearCart();
        cart.addItem(product());
        cart.addItem(product({ id: 2, name: 'Chips', price: 50 }));
    });

    it('has no rule discount until a preview arrives', () => {
        expect(cart.subtotal.value).toBe(150);
        expect(cart.rulesDiscount.value).toBe(0);
        expect(cart.total.value).toBe(150);
    });

    it('takes the rule discount off the total', () => {
        cart.setRulesPreview({ total: 20, coupon_status: 'none', lines: [{ key: 0, discount: 20, applied: [{ rule_id: 1, rule_name: 'Ten off', amount: 20 }] }, { key: 1, discount: 0, applied: [] }] });

        expect(cart.rulesDiscount.value).toBe(20);
        expect(cart.total.value).toBe(130);
        expect(cart.rulesForItem(0).applied[0].rule_name).toBe('Ten off');
        expect(cart.rulesForItem(5)).toBeNull();
    });

    it('applies the manual discount to what is left after the rules', () => {
        cart.setRulesPreview({ total: 50, coupon_status: 'none', lines: [] });
        cart.setDiscount('percentage', 10);

        expect(cart.discountAmount.value).toBe(10);
        expect(cart.total.value).toBe(90);

        cart.setDiscount('fixed', 500);
        expect(cart.discountAmount.value).toBe(100);
        expect(cart.total.value).toBe(0);
    });

    it('never lets rules push the total below zero', () => {
        cart.setRulesPreview({ total: 9999, coupon_status: 'none', lines: [] });

        expect(cart.total.value).toBe(0);
    });

    it('sends the coupon code with the checkout payload and clears it with the cart', () => {
        cart.couponCode.value = '  spring ';

        expect(cart.getCheckoutPayload().coupon_code).toBe('spring');

        cart.clearCart();
        expect(cart.couponCode.value).toBe('');
        expect(cart.rulesPreview.value).toBeNull();
        expect(cart.getCheckoutPayload().coupon_code).toBeNull();
    });

    it('keeps the coupon in tab snapshots', () => {
        cart.couponCode.value = 'KEEP';
        const snapshot = cart.snapshotCart();

        cart.clearCart();
        cart.restoreCart(snapshot);

        expect(cart.couponCode.value).toBe('KEEP');
        expect(cart.cartItems.value).toHaveLength(2);
    });

    it('forgets the customer and payment type once the sale is cleared', () => {
        cart.selectedCustomerId.value = 7;
        cart.paymentType.value = 'debt';

        cart.clearCart();

        expect(cart.selectedCustomerId.value).toBeNull();
        expect(cart.paymentType.value).toBe('cash');
    });

    it('keeps each tab customer and payment type in its snapshot', () => {
        cart.selectedCustomerId.value = 7;
        cart.paymentType.value = 'cash';
        const snapshot = cart.snapshotCart();

        cart.clearCart();
        expect(cart.selectedCustomerId.value).toBeNull();

        cart.restoreCart(snapshot);
        expect(cart.selectedCustomerId.value).toBe(7);
    });
});
