import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { Pencil } from 'lucide-vue-next';
import IconButton from '../IconButton.vue';

const InertiaLinkStub = {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
};

const mountButton = (props = {}) =>
    mount(IconButton, {
        props: { icon: Pencil, label: 'Edit', ...props },
        global: { stubs: { Link: InertiaLinkStub } },
    });

describe('IconButton', () => {
    it('renders a button with an accessible name and tooltip', () => {
        const wrapper = mountButton();

        expect(wrapper.element.tagName).toBe('BUTTON');
        expect(wrapper.attributes('aria-label')).toBe('Edit');
        expect(wrapper.attributes('title')).toBe('Edit');
        expect(wrapper.find('svg').exists()).toBe(true);
    });

    it('renders a link when href is given', () => {
        const wrapper = mountButton({ href: '/products/1/edit' });

        expect(wrapper.element.tagName).toBe('A');
        expect(wrapper.attributes('href')).toBe('/products/1/edit');
        expect(wrapper.attributes('aria-label')).toBe('Edit');
    });

    it('emits a native click for button usage', async () => {
        const wrapper = mountButton();

        await wrapper.trigger('click');

        expect(wrapper.emitted('click')).toHaveLength(1);
    });

    it('uses the danger colour on hover for the danger variant', () => {
        const wrapper = mountButton({ variant: 'danger' });

        expect(wrapper.classes().join(' ')).toContain('hover:text-status-danger');
    });
});
