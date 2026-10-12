import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ConditionBuilder from '@modules/Discounts/Resources/js/Components/ConditionBuilder.vue';
import { blankCondition, changeField, changeOperator, defaultValue } from '@modules/Discounts/Resources/js/Components/conditionTree';
import { i18n } from '@/Components/__tests__/helpers';

const facts = [
    { key: 'item.quantity', scope: 'item', type: 'number', operators: ['eq', 'gte', 'between'], window: false },
    { key: 'customer.orders_count', scope: 'customer', type: 'number', operators: ['gte', 'lte'], window: true },
    { key: 'item.category_id', scope: 'item', type: 'id', operators: ['eq', 'in', 'not_in'], window: false },
    { key: 'customer.is_selected', scope: 'customer', type: 'bool', operators: ['eq'], window: false },
];
const lookups = { 'item.category_id': [{ id: 1, name: 'Drinks' }, { id: 2, name: 'Snacks' }] };

const mountBuilder = (modelValue) =>
    mount(ConditionBuilder, { props: { modelValue, facts, lookups }, global: { plugins: [i18n] } });

const emptyGroup = () => ({ type: 'group', logic: 'all', children: [] });

describe('conditionTree helpers', () => {
    it('starts a condition on the first fact with its first operator', () => {
        expect(blankCondition(facts)).toEqual({ type: 'condition', field: 'item.quantity', operator: 'eq', value: '', params: {} });
    });

    it('gives each operator the right kind of empty value', () => {
        expect(defaultValue(facts[0], 'between')).toEqual(['', '']);
        expect(defaultValue(facts[2], 'in')).toEqual([]);
        expect(defaultValue(facts[3], 'eq')).toBe(true);
        expect(defaultValue(facts[0], 'gte')).toBe('');
    });

    it('keeps the operator across facts when it is still allowed and resets the value and window', () => {
        const condition = { type: 'condition', field: 'item.quantity', operator: 'gte', value: 5, params: { window_days: 7 } };

        expect(changeField(condition, facts[1])).toMatchObject({ field: 'customer.orders_count', operator: 'gte', value: '', params: {} });
        expect(changeField(condition, facts[2])).toMatchObject({ operator: 'eq', value: '' });
    });

    it('turns a value into a list or range when the operator needs one', () => {
        const condition = { type: 'condition', field: 'item.category_id', operator: 'eq', value: 3, params: {} };

        expect(changeOperator(condition, facts[2], 'in').value).toEqual([]);
        expect(changeOperator({ ...condition, operator: 'in', value: [1] }, facts[2], 'not_in').value).toEqual([1]);
        expect(changeOperator({ ...condition, field: 'item.quantity', operator: 'gte', value: 4 }, facts[0], 'between').value).toEqual(['', '']);
    });
});

describe('ConditionBuilder', () => {
    it('adds a condition to the root group', async () => {
        const wrapper = mountBuilder(emptyGroup());

        await wrapper.findAll('button').find((button) => button.text().includes('Add condition')).trigger('click');

        const emitted = wrapper.emitted('update:modelValue').at(-1)[0];
        expect(emitted.children).toHaveLength(1);
        expect(emitted.children[0]).toMatchObject({ type: 'condition', field: 'item.quantity' });
    });

    it('adds a nested group and offers removing it', async () => {
        const wrapper = mountBuilder(emptyGroup());

        await wrapper.findAll('button').find((button) => button.text().includes('Add group')).trigger('click');

        expect(wrapper.emitted('update:modelValue').at(-1)[0].children[0]).toMatchObject({ type: 'group', logic: 'all', children: [] });
    });

    it('renders conditions and groups from the model', () => {
        const wrapper = mountBuilder({
            type: 'group', logic: 'any', children: [
                { type: 'condition', field: 'item.quantity', operator: 'gte', value: 10, params: {} },
                { type: 'group', logic: 'all', children: [{ type: 'condition', field: 'customer.is_selected', operator: 'eq', value: true, params: {} }] },
            ],
        });

        expect(wrapper.text()).toContain('Match');
        expect(wrapper.findAll('input[type="number"]')[0].element.value).toBe('10');
        expect(wrapper.text()).toContain('A customer is selected');
    });

    it('shows chosen list values as removable chips and removes one', async () => {
        const wrapper = mountBuilder({
            type: 'group', logic: 'all', children: [{ type: 'condition', field: 'item.category_id', operator: 'in', value: [1, 2], params: {} }],
        });

        expect(wrapper.text()).toContain('Drinks');
        expect(wrapper.text()).toContain('Snacks');

        await wrapper.findAll('button[aria-label]').find((button) => button.element.closest('span')?.textContent.includes('Drinks')).trigger('click');

        expect(wrapper.emitted('update:modelValue').at(-1)[0].children[0].value).toEqual([2]);
    });

    it('lets the time window be set on history facts', async () => {
        const wrapper = mountBuilder({
            type: 'group', logic: 'all', children: [{ type: 'condition', field: 'customer.orders_count', operator: 'gte', value: 10, params: {} }],
        });

        const windowInput = wrapper.findAll('input[type="number"]')[1];
        await windowInput.setValue('30');

        expect(wrapper.emitted('update:modelValue').at(-1)[0].children[0].params).toEqual({ window_days: 30 });
    });

    it('removes a condition', async () => {
        const wrapper = mountBuilder({
            type: 'group', logic: 'all', children: [{ type: 'condition', field: 'item.quantity', operator: 'eq', value: 1, params: {} }],
        });

        await wrapper.find('button[aria-label="Remove condition"]').trigger('click');

        expect(wrapper.emitted('update:modelValue').at(-1)[0].children).toEqual([]);
    });
});
