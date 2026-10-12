import { describe, expect, it } from 'vitest';
import messages from '../messages';

const sources = {
    ...import.meta.glob(['../../**/*.vue', '../../**/*.js', '!../../**/__tests__/**', '!../../i18n/**'], { query: '?raw', import: 'default', eager: true }),
    ...import.meta.glob(['../../../../app/Modules/**/Resources/js/**/*.{vue,js}', '../../../../vendor/tessera/**/Resources/js/**/*.{vue,js}', '!../../../../**/__tests__/**'], { query: '?raw', import: 'default', eager: true }),
};

const lookup = (tree, key) => key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), tree);

describe('translation keys used in the source', () => {
    it('all exist in English, wherever the module keeps them', () => {
        const missing = [];

        for (const [path, source] of Object.entries(sources)) {
            for (const match of source.matchAll(/\bt\(\s*'([A-Za-z]+(?:\.[A-Za-z0-9_]+)+)'/g)) {
                if (lookup(messages.en, match[1]) === undefined) {
                    missing.push(`${match[1]} (${path.replace(/^.*\/(app\/Modules|resources)\//, '$1/')})`);
                }
            }
        }

        expect([...new Set(missing)]).toEqual([]);
    });
});
