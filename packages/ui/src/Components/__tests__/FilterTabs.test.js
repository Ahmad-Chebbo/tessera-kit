import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import FilterTabs from '../FilterTabs.vue';

const tabs = [
    { value: '', label: 'All', count: 16 },
    { value: 'a', label: 'MANA2ESH', count: 0 },
    { value: 'b', label: 'SHAWARMA' },
];

describe('FilterTabs', () => {
    it('renders every tab, including a zero count, and omits missing counts', () => {
        const wrapper = mount(FilterTabs, { props: { modelValue: '', tabs } });
        const text = wrapper.findAll('[role="tab"]').map((tab) => tab.text());

        expect(text).toEqual(['All 16', 'MANA2ESH 0', 'SHAWARMA']);
    });

    it('marks the active tab as selected', () => {
        const wrapper = mount(FilterTabs, { props: { modelValue: 'a', tabs } });
        const selected = wrapper.findAll('[role="tab"]').map((tab) => tab.attributes('aria-selected'));

        expect(selected).toEqual(['false', 'true', 'false']);
    });

    it('treats null and empty string as the same "all" value', () => {
        const wrapper = mount(FilterTabs, { props: { modelValue: null, tabs } });

        expect(wrapper.findAll('[role="tab"]')[0].attributes('aria-selected')).toBe('true');
    });

    it('emits the tab value on click', async () => {
        const wrapper = mount(FilterTabs, { props: { modelValue: '', tabs } });

        await wrapper.findAll('[role="tab"]')[2].trigger('click');

        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['b']);
    });

    it('moves selection with the arrow keys', async () => {
        const wrapper = mount(FilterTabs, { props: { modelValue: 'a', tabs } });

        await wrapper.findAll('[role="tab"]')[1].trigger('keydown', { key: 'ArrowRight' });

        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['b']);
    });

    it('moves focus along with the selection so keyboard navigation can continue', async () => {
        const wrapper = mount(FilterTabs, { props: { modelValue: '', tabs }, attachTo: document.body });
        const buttons = () => wrapper.findAll('[role="tab"]');

        buttons()[0].element.focus();
        await buttons()[0].trigger('keydown', { key: 'ArrowRight' });
        expect(document.activeElement).toBe(buttons()[1].element);

        await buttons()[1].trigger('keydown', { key: 'ArrowRight' });
        expect(document.activeElement).toBe(buttons()[2].element);
        expect(wrapper.emitted('update:modelValue')).toEqual([['a'], ['b']]);

        wrapper.unmount();
    });

    it('jumps to the first and last tab with Home and End', async () => {
        const wrapper = mount(FilterTabs, { props: { modelValue: 'a', tabs }, attachTo: document.body });

        await wrapper.findAll('[role="tab"]')[1].trigger('keydown', { key: 'End' });
        await wrapper.findAll('[role="tab"]')[1].trigger('keydown', { key: 'Home' });

        expect(wrapper.emitted('update:modelValue')).toEqual([['b'], ['']]);

        wrapper.unmount();
    });
});
