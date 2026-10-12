import { describe, expect, it } from 'vitest';
import { moduleAliases, tesseraModules } from '../../../vite.modules.js';

const slashes = (path) => path.split('\\').join('/');

describe('moduleAliases', () => {
    it('always ends with the app/Modules fallback', () => {
        const aliases = moduleAliases();
        const last = aliases[aliases.length - 1];

        expect(last.find).toBe('@modules');
        expect(slashes(last.replacement)).toMatch(/\/app\/Modules$/);
    });

    it('sends only the exact module name to an installed package, never a longer name', () => {
        for (const { find, replacement } of moduleAliases().slice(0, -1)) {
            const name = find.source.match(/@modules\\?\/(\w+)/)[1];

            expect(slashes(replacement)).toContain('/vendor/tessera/');
            expect(find.test(`@modules/${name}/Resources/x.vue`)).toBe(true);
            expect(find.test(`@modules/${name}Plus/x.vue`)).toBe(false);
        }
    });
});

describe('tesseraModules', () => {
    const resolve = (source) => {
        const seen = [];
        const result = tesseraModules().resolveId.call({ resolve: (path) => (seen.push(path), path) }, source, 'importer.vue', {});

        return { result, seen };
    };

    it('leaves other imports alone', () => {
        expect(resolve('vue').result).toBeNull();
        expect(resolve('@/Layouts/SidebarLayout.vue').result).toBeNull();
    });

    it('falls back to app/Modules for a module that is not installed in vendor', () => {
        expect(slashes(resolve('@modules/Invoices/Resources/x.vue').result)).toMatch(/\/app\/Modules\/Invoices\/Resources\/x\.vue$/);
    });
});
