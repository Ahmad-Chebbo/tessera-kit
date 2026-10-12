import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { h } from 'vue';
import Select from '../Select.vue';

const mountSelect = (props = {}, slots = {}) =>
    mount(Select, {
        props,
        slots,
        attachTo: document.body,
        global: { stubs: { teleport: true } },
    });

const options = [
    { value: 1, label: 'MANA2ESH' },
    { value: 2, label: 'SHAWARMA' },
    { value: 3, label: 'Sushi', disabled: true },
];

describe('Select', () => {
    it('shows the placeholder when nothing is selected', () => {
        const wrapper = mountSelect({ modelValue: '', options, placeholder: 'Pick one' });

        expect(wrapper.get('button').text()).toContain('Pick one');
    });

    it('shows the label of the selected option', () => {
        const wrapper = mountSelect({ modelValue: 2, options });

        expect(wrapper.get('button').text()).toContain('SHAWARMA');
    });

    it('matches values loosely so "2" selects the numeric 2', () => {
        const wrapper = mountSelect({ modelValue: '2', options });

        expect(wrapper.get('button').text()).toContain('SHAWARMA');
    });

    it('reads native <option> children, including v-for fragments and disabled', async () => {
        const wrapper = mountSelect(
            { modelValue: '' },
            {
                default: () => [
                    h('option', { value: '' }, 'All'),
                    [h('option', { value: 7 }, 'Seven'), h('option', { value: 8, disabled: true }, 'Eight')],
                ],
            },
        );

        await wrapper.get('button').trigger('click');
        const items = wrapper.findAll('[role="option"]');

        expect(items.map((item) => item.text())).toEqual(['All', 'Seven', 'Eight']);
        expect(items[2].attributes('aria-disabled')).toBe('true');
    });

    it('opens on click, emits update:modelValue and change, then closes', async () => {
        const wrapper = mountSelect({ modelValue: 1, options });

        await wrapper.get('button').trigger('click');
        expect(wrapper.find('[role="listbox"]').exists()).toBe(true);

        await wrapper.findAll('[role="option"]')[1].trigger('click');

        expect(wrapper.emitted('update:modelValue')[0]).toEqual([2]);
        expect(wrapper.emitted('change')[0]).toEqual([2]);
        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it('does not select a disabled option', async () => {
        const wrapper = mountSelect({ modelValue: 1, options });

        await wrapper.get('button').trigger('click');
        await wrapper.findAll('[role="option"]')[2].trigger('click');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect(wrapper.find('[role="listbox"]').exists()).toBe(true);
    });

    it('supports keyboard selection with arrows and Enter, skipping disabled options', async () => {
        const wrapper = mountSelect({ modelValue: 1, options });
        const trigger = wrapper.get('button');

        await trigger.trigger('keydown', { key: 'ArrowDown' });
        await trigger.trigger('keydown', { key: 'ArrowDown' });
        await trigger.trigger('keydown', { key: 'ArrowDown' });
        await trigger.trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('update:modelValue')[0]).toEqual([2]);
    });

    it('closes on Escape without changing the value', async () => {
        const wrapper = mountSelect({ modelValue: 1, options });

        await wrapper.get('button').trigger('click');
        await wrapper.get('button').trigger('keydown', { key: 'Escape' });

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('does not open when disabled', async () => {
        const wrapper = mountSelect({ modelValue: 1, options, disabled: true });

        await wrapper.get('button').trigger('click');

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it('keeps browser validation for required selects via a hidden input', async () => {
        const empty = mountSelect({ modelValue: '', options: [{ value: '', label: '' }, ...options], required: true });
        const hidden = empty.get('input[required]');
        expect(hidden.element.value).toBe('');

        const filled = mountSelect({ modelValue: 2, options, required: true });
        expect(filled.get('input[required]').element.value).toBe('2');

        expect(mountSelect({ modelValue: 1, options }).find('input').exists()).toBe(false);
    });

    it('does not emit change when the current value is picked again', async () => {
        const wrapper = mountSelect({ modelValue: 2, options });

        await wrapper.get('button').trigger('click');
        await wrapper.findAll('[role="option"]')[1].trigger('click');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect(wrapper.emitted('change')).toBeUndefined();
        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it('announces the highlighted option to assistive tech', async () => {
        const wrapper = mountSelect({ modelValue: 1, options });
        const trigger = wrapper.get('button');

        await trigger.trigger('click');
        const items = wrapper.findAll('[role="option"]');
        const active = trigger.attributes('aria-activedescendant');

        expect(active).toBeTruthy();
        expect(items[0].attributes('id')).toBe(active);
        expect(trigger.attributes('aria-controls')).toBe(wrapper.get('[role="listbox"]').attributes('id'));

        await trigger.trigger('keydown', { key: 'ArrowDown' });
        expect(trigger.attributes('aria-activedescendant')).toBe(items[1].attributes('id'));
    });

    it('offers a compact size for dense layouts', () => {
        const wrapper = mountSelect({ modelValue: 1, options, size: 'sm' });

        expect(wrapper.get('button').classes()).toContain('text-xs');
    });

    it('renders its panel inside an enclosing <dialog> so it is not hidden behind the modal top layer', async () => {
        const dialog = document.createElement('dialog');
        document.body.appendChild(dialog);

        const wrapper = mount(Select, { props: { modelValue: 1, options }, attachTo: dialog });
        await wrapper.get('button').trigger('click');

        expect(dialog.querySelector('[role="listbox"]')).not.toBeNull();

        wrapper.unmount();
        dialog.remove();
    });
});
