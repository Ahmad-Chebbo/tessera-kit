import { describe, expect, it } from 'vitest';
import { moduleAliases } from '../../../vite.modules.js';

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

            expect(slashes(replacement)).toContain('/vendor/ahmad-chebbo/');
            expect(find.test(`@modules/${name}/Resources/x.vue`)).toBe(true);
            expect(find.test(`@modules/${name}Plus/x.vue`)).toBe(false);
        }
    });
});
