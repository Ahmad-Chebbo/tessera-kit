import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { defineComponent, h } from 'vue';

const page = { props: {} };

vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

const { useNavigation } = await import('../useNavigation');

const i18n = createI18n({
    legacy: false,
    locale: 'en',
    messages: { en: { nav: { dashboard: 'Dashboard', products: 'Products' } } },
});

function navigationFor(navigation) {
    page.props = { navigation };
    let result;

    mount(defineComponent({
        setup() {
            result = useNavigation();

            return () => h('div');
        },
    }), { global: { plugins: [i18n] } });

    return result.value;
}

describe('useNavigation', () => {
    it('translates standalone entries and keeps their link and state', () => {
        expect(navigationFor([{ label: 'nav.dashboard', href: '/dashboard', icon: 'home', active: true }])).toEqual([
            { name: 'Dashboard', href: '/dashboard', icon: 'home', active: true },
        ]);
    });

    it('translates the entries inside sections and keeps the section title', () => {
        expect(navigationFor([{ section: 'CATALOG', items: [{ label: 'nav.products', href: '/products', icon: 'package', active: false }] }])).toEqual([
            { section: 'CATALOG', items: [{ name: 'Products', href: '/products', icon: 'package', active: false }] },
        ]);
    });

    it('is empty when the server sent no navigation', () => {
        expect(navigationFor(undefined)).toEqual([]);
    });
});
