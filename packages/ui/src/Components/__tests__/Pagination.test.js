import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import Pagination from '../Pagination.vue';
import { i18n } from './helpers';

const InertiaLinkStub = {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
};

const paginator = (links) => ({ links, from: 1, to: 15, total: 40 });

const page = (label, url, active = false) => ({ label, url, active });

const mountPagination = (props) =>
    mount(Pagination, { props, global: { plugins: [i18n], stubs: { Link: InertiaLinkStub } } });

describe('Pagination', () => {
    it('renders nothing when there is a single page', () => {
        const wrapper = mountPagination({
            paginator: paginator([page('&laquo; Previous', null), page('1', '/?page=1', true), page('Next &raquo;', null)]),
        });

        expect(wrapper.find('nav').exists()).toBe(false);
    });

    it('renders links, marks the active page and shows a summary', () => {
        const wrapper = mountPagination({
            paginator: paginator([
                page('&laquo; Previous', null),
                page('1', '/?page=1', true),
                page('2', '/?page=2'),
                page('Next &raquo;', '/?page=2'),
            ]),
        });

        expect(wrapper.text()).toContain('1 to 15');
        expect(wrapper.text()).toContain('40');

        const links = wrapper.findAll('a');
        expect(links.map((link) => link.attributes('href'))).toEqual(['/?page=1', '/?page=2', '/?page=2']);
        expect(links[0].classes().join(' ')).toContain('bg-gold');
        expect(links[1].classes().join(' ')).not.toContain('bg-gold');
    });

    it('renders unavailable pages as inert text, not links', () => {
        const wrapper = mountPagination({
            paginator: paginator([
                page('&laquo; Previous', null),
                page('1', '/?page=1', true),
                page('2', '/?page=2'),
                page('Next &raquo;', '/?page=2'),
            ]),
        });

        const inert = wrapper.findAll('span[aria-disabled="true"]');
        expect(inert).toHaveLength(1);
        expect(inert[0].text()).toContain('«');
    });

    it('drops the card border when not bordered', () => {
        const wrapper = mountPagination({
            bordered: false,
            paginator: paginator([page('&laquo;', null), page('1', '/1', true), page('2', '/2'), page('&raquo;', '/2')]),
        });

        expect(wrapper.classes().join(' ')).not.toContain('border-t');
    });
});
