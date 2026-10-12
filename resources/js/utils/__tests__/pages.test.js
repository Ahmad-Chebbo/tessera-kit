import { describe, expect, it } from 'vitest';
import { indexPages, resolvePage } from '../pages';

describe('indexPages', () => {
    it('names pages by the path after Pages, wherever the module keeps them', () => {
        const pages = indexPages(
            { './Pages/Auth/Login.vue': () => 'core login' },
            { '../../app/Modules/Expenses/Resources/js/Pages/Expenses/Index.vue': () => 'expenses' },
        );

        expect(Object.keys(pages)).toEqual(['Auth/Login', 'Expenses/Index']);
    });

    it('refuses two modules that provide the same page', () => {
        expect(() => indexPages(
            {},
            { '../../app/Modules/Expenses/Resources/js/Pages/Expenses/Index.vue': () => 1 },
            { '../../vendor/tessera/tessera-expenses/Resources/js/Pages/Expenses/Index.vue': () => 2 },
        )).toThrow('Two files provide the page "Expenses/Index"');
    });

    it('lets a page of the project replace the page of a module with the same name', () => {
        const pages = indexPages(
            { './Pages/Expenses/Index.vue': () => 'mine' },
            { '../../vendor/tessera/tessera-expenses/Resources/js/Pages/Expenses/Index.vue': () => 'module', '../../vendor/tessera/tessera-expenses/Resources/js/Pages/Expenses/Create.vue': () => 'create' },
        );

        expect(resolvePage(pages, 'Expenses/Index')).toBe('mine');
        expect(resolvePage(pages, 'Expenses/Create')).toBe('create');
    });

    it('loads a page by name and fails clearly for an unknown one', () => {
        const pages = indexPages({ './Pages/Dashboard.vue': () => 'dashboard' });

        expect(resolvePage(pages, 'Dashboard')).toBe('dashboard');
        expect(() => resolvePage(pages, 'Missing')).toThrow('Page not found: Missing');
    });
});
