import { describe, expect, it } from 'vitest';
import { collectMessages, coreMessages, moduleMessageFiles } from '../messages';

const CORE_MODULES = ['Auth', 'Tenant', 'Subscription', 'Team'];
const isCoreModule = (path) => CORE_MODULES.some((module) => path.includes(`/modules/${module}/`) || path.includes(`/Modules/${module}/`));

const moduleSources = import.meta.glob(['../../../../app/Modules/*/Resources/js/**/*.{vue,js}', '../../../../vendor/tessera/*/Resources/js/**/*.{vue,js}', '../../../../vendor/tessera/*/modules/*/Resources/js/**/*.{vue,js}', '!../../../../**/__tests__/**'], { query: '?raw', import: 'default', eager: true });

const coreSources = {
    ...import.meta.glob(['../../**/*.vue', '../../**/*.js', '!../../**/__tests__/**', '!../../i18n/**'], { query: '?raw', import: 'default', eager: true }),
    ...Object.fromEntries(Object.entries(moduleSources).filter(([path]) => isCoreModule(path))),
};

const lookup = (tree, key) => key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), tree);

describe('core translations', () => {
    it('are enough for the core screens when no feature module is installed', () => {
        const coreOnly = collectMessages(
            Object.fromEntries(Object.entries(moduleMessageFiles).filter(([path]) => isCoreModule(path))),
            coreMessages,
        );
        const missing = [];

        for (const [path, source] of Object.entries(coreSources)) {
            for (const match of source.matchAll(/\bt\(\s*'([A-Za-z]+(?:\.[A-Za-z0-9_]+)+)'/g)) {
                if (lookup(coreOnly.en, match[1]) === undefined) {
                    missing.push(`${match[1]} (${path.replace(/^.*\/(app\/Modules|resources)\//, '$1/')})`);
                }
            }
        }

        expect([...new Set(missing)]).toEqual([]);
    });
});
