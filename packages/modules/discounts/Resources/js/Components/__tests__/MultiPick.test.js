import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import MultiPick from '@modules/Discounts/Resources/js/Components/MultiPick.vue';

const options = [
    { id: 1, name: 'Cola' },
    { id: 2, name: 'Chips' },
    { id: 3, name: 'Juice' },
];

const mountPick = (modelValue = []) => mount(MultiPick, { props: { modelValue, options, placeholder: 'Add' } });

describe('MultiPick', () => {
    it('shows the picked options as removable chips', () => {
        const wrapper = mountPick([1, 3]);

        expect(wrapper.findAll('[data-chip]').map((chip) => chip.text())).toEqual(['Cola', 'Juice']);
    });

    it('removes a chip', async () => {
        const wrapper = mountPick([1, 3]);

        await wrapper.find('button[aria-label="Remove Cola"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')[0][0]).toEqual([3]);
    });

    it('adds the chosen option as a number', async () => {
        const wrapper = mountPick([1]);

        wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', '2');

        expect(wrapper.emitted('update:modelValue')[0][0]).toEqual([1, 2]);
    });

    it('ignores an empty choice', () => {
        const wrapper = mountPick([1]);

        wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', '');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('hides the picker once everything is chosen', () => {
        expect(mountPick([1, 2, 3]).findComponent({ name: 'Select' }).exists()).toBe(false);
    });

    it('falls back to the id for an unknown option', () => {
        expect(mountPick([99]).find('[data-chip]').text()).toBe('99');
    });
});
