import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CardGrid from '../CardGrid.vue';
import { i18n } from './helpers';

const InertiaLinkStub = {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
};

const items = [
    { id: 1, name: 'Shawarma' },
    { id: 2, name: 'Sushi' },
];

const mountGrid = (props = {}, slots = {}) =>
    mount(CardGrid, {
        props: { items, ...props },
        slots: {
            item: `<template #item="{ item, index }"><article data-card>{{ index }}-{{ item.name }}</article></template>`,
            ...slots,
        },
        global: { plugins: [i18n], stubs: { Link: InertiaLinkStub } },
    });

describe('CardGrid', () => {
    it('renders the item slot once per item with the item and index', () => {
        const wrapper = mountGrid();

        expect(wrapper.findAll('[data-card]').map((card) => card.text())).toEqual(['0-Shawarma', '1-Sushi']);
    });

    it('uses a responsive grid by default and accepts custom column classes', () => {
        expect(mountGrid().get('[data-grid]').classes()).toEqual(expect.arrayContaining(['grid', 'lg:grid-cols-3']));
        expect(mountGrid({ cols: 'sm:grid-cols-2' }).get('[data-grid]').classes()).toContain('sm:grid-cols-2');
    });

    it('shows an empty state instead of the grid when there are no items', () => {
        const wrapper = mountGrid({ items: [], emptyTitle: 'No products yet' });

        expect(wrapper.find('[data-grid]').exists()).toBe(false);
        expect(wrapper.text()).toContain('No products yet');
    });

    it('keys items by property name or function', () => {
        expect(() => mountGrid({ itemKey: 'name' })).not.toThrow();
        expect(() => mountGrid({ itemKey: (item) => `i-${item.id}` })).not.toThrow();
    });

    it('renders borderless pagination below the grid', () => {
        const wrapper = mountGrid({
            paginator: {
                from: 1,
                to: 2,
                total: 40,
                links: [
                    { label: '&laquo;', url: null, active: false },
                    { label: '1', url: '/?page=1', active: true },
                    { label: '2', url: '/?page=2', active: false },
                    { label: '&raquo;', url: '/?page=2', active: false },
                ],
            },
        });

        expect(wrapper.find('nav a').exists()).toBe(true);
        expect(wrapper.html()).not.toContain('border-t');
    });
});
