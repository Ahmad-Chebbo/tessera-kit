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

    it('refuses two files with the same page name', () => {
        expect(() => indexPages(
            { './Pages/Expenses/Index.vue': () => 1 },
            { '../../app/Modules/Expenses/Resources/js/Pages/Expenses/Index.vue': () => 2 },
        )).toThrow('Two files provide the page "Expenses/Index"');
    });

    it('loads a page by name and fails clearly for an unknown one', () => {
        const pages = indexPages({ './Pages/Dashboard.vue': () => 'dashboard' });

        expect(resolvePage(pages, 'Dashboard')).toBe('dashboard');
        expect(() => resolvePage(pages, 'Missing')).toThrow('Page not found: Missing');
    });
});
