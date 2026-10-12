import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import DataTable from '../DataTable.vue';
import { i18n } from './helpers';

const InertiaLinkStub = {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
};

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'price', label: 'Price', align: 'end', cellClass: 'font-medium' },
    { key: 'note', label: 'Note', wrap: true },
];

const rows = [
    { id: 1, name: 'Shawarma', price: 450, note: 'Chicken' },
    { id: 2, name: 'Sushi', price: 900, note: 'Salmon' },
];

const mountTable = (props = {}, slots = {}) =>
    mount(DataTable, {
        props: { columns, rows, ...props },
        slots,
        global: { plugins: [i18n], stubs: { Link: InertiaLinkStub } },
    });

describe('DataTable', () => {
    it('renders a header cell per column and a row per record with plain values', () => {
        const wrapper = mountTable();

        expect(wrapper.findAll('th').map((th) => th.text())).toEqual(['Name', 'Price', 'Note']);
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        expect(wrapper.findAll('tbody tr')[0].findAll('td').map((td) => td.text())).toEqual(['Shawarma', '450', 'Chicken']);
    });

    it('lets a cell slot render custom content with the row and index', () => {
        const wrapper = mountTable(
            {},
            {
                'cell-name': `<template #cell-name="{ row, index }"><b>{{ index }}:{{ row.name }}</b></template>`,
            },
        );

        expect(wrapper.findAll('tbody tr')[1].find('b').text()).toBe('1:Sushi');
    });

    it('lets a header slot replace the label', () => {
        const wrapper = mountTable({}, { 'head-price': '<template #head-price><i>Cost</i></template>' });

        expect(wrapper.findAll('th')[1].find('i').text()).toBe('Cost');
    });

    it('aligns, styles and wraps cells per column', () => {
        const wrapper = mountTable();
        const [name, price, note] = wrapper.findAll('tbody tr')[0].findAll('td');

        expect(wrapper.findAll('th')[1].classes()).toContain('text-end');
        expect(price.classes()).toEqual(expect.arrayContaining(['text-end', 'font-medium']));
        expect(name.classes()).toContain('whitespace-nowrap');
        expect(note.classes()).not.toContain('whitespace-nowrap');
    });

    it('shows the empty message across every column when there are no rows', () => {
        const wrapper = mountTable({ rows: [], emptyTitle: 'Nothing here' });
        const empty = wrapper.get('tbody td[colspan]');

        expect(empty.attributes('colspan')).toBe('3');
        expect(empty.text()).toBe('Nothing here');
    });

    it('lets the empty slot replace the empty message', () => {
        const wrapper = mountTable({ rows: [] }, { empty: '<strong>No data</strong>' });

        expect(wrapper.get('tbody td[colspan] strong').text()).toBe('No data');
    });

    it('renders no empty row when there is neither a message nor a slot', () => {
        const wrapper = mountTable({ rows: [] });

        expect(wrapper.findAll('tbody tr')).toHaveLength(0);
    });

    it('keys rows by a property name or a function', () => {
        expect(() => mountTable({ rowKey: 'name' })).not.toThrow();
        expect(() => mountTable({ rowKey: (row) => `row-${row.id}` })).not.toThrow();
    });

    it('applies a row class function', () => {
        const wrapper = mountTable({ rowClass: (row) => (row.price > 500 ? 'bg-accent-wash' : '') });
        const trs = wrapper.findAll('tbody tr');

        expect(trs[0].classes()).not.toContain('bg-accent-wash');
        expect(trs[1].classes()).toContain('bg-accent-wash');
    });

    it('renders pagination inside the card when a paginator is given', () => {
        const wrapper = mountTable({
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
    });

    it('drops the card chrome when card is false', () => {
        const withCard = mountTable();
        const without = mountTable({ card: false });

        expect(withCard.classes()).toContain('rounded-2xl');
        expect(without.classes()).not.toContain('rounded-2xl');
    });

    it('lets a column replace the default cell and header padding', () => {
        const wrapper = mountTable({
            columns: [{ key: 'name', label: 'Name', cellClass: 'px-4 py-2', headerClass: 'px-4 py-2' }],
        });

        const th = wrapper.get('th');
        const td = wrapper.get('tbody td');

        expect(td.classes()).toEqual(expect.arrayContaining(['px-4', 'py-2']));
        expect(td.classes()).not.toContain('px-6');
        expect(td.classes()).not.toContain('py-4');
        expect(th.classes()).not.toContain('px-6');
        expect(th.classes()).not.toContain('py-3');
    });

    it('recognises logical, shorthand and responsive padding utilities as overrides', () => {
        const wrapper = mountTable({
            columns: [
                { key: 'name', label: 'A', cellClass: 'ps-2 pe-2 py-2', headerClass: 'p-2' },
                { key: 'price', label: 'B', cellClass: 'sm:px-4 pt-1 pb-1' },
            ],
        });

        const [first, second] = wrapper.findAll('tbody td');
        expect(first.classes()).not.toContain('px-6');
        expect(first.classes()).not.toContain('py-4');
        expect(second.classes()).not.toContain('px-6');
        expect(second.classes()).not.toContain('py-4');
        expect(wrapper.get('th').classes()).not.toContain('px-6');
        expect(wrapper.get('th').classes()).not.toContain('py-3');
    });
});
