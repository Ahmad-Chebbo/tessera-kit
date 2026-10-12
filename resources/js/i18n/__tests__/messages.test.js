import { describe, expect, it } from 'vitest';
import messages, { collectMessages, coreMessages, leafPaths, mergeMessages, moduleMessageFiles } from '../messages';

describe('mergeMessages', () => {
    it('adds module keys next to core keys, at any depth', () => {
        expect(mergeMessages({ nav: { home: 'Home' } }, { nav: { products: 'Products' }, expenses: { nav: 'Expenses' } })).toEqual({
            nav: { home: 'Home', products: 'Products' },
            expenses: { nav: 'Expenses' },
        });
    });
});

describe('collectMessages', () => {
    it('reads the locale from the file name and merges modules in path order', () => {
        const result = collectMessages(
            {
                '../../../app/Modules/Sales/Resources/lang/en.json': { pos: { title: 'POS' } },
                '../../../app/Modules/Sales/Resources/lang/ar.json': { pos: { title: 'نقطة البيع' } },
            },
            { en: { common: { save: 'Save' } }, ar: { common: { save: 'حفظ' } } },
        );

        expect(result.en).toEqual({ common: { save: 'Save' }, pos: { title: 'POS' } });
        expect(result.ar.pos.title).toBe('نقطة البيع');
    });
});

describe('the shipped translations', () => {
    it('have the same keys in English and Arabic', () => {
        expect(leafPaths(messages.ar).sort()).toEqual(leafPaths(messages.en).sort());
    });

    it('define every key in one place only', () => {
        const seen = new Map();
        const clashes = [];
        const sources = [
            ...Object.entries(coreMessages).map(([locale, tree]) => [`core/${locale}`, locale, tree]),
            ...Object.entries(moduleMessageFiles).map(([path, tree]) => [path, path.match(/\/([^/]+)\.json$/)[1], tree]),
        ];

        for (const [source, locale, tree] of sources) {
            for (const leaf of leafPaths(tree)) {
                const id = `${locale}:${leaf}`;

                if (seen.has(id)) {
                    clashes.push(`${leaf} (${seen.get(id)} and ${source})`);
                }

                seen.set(id, source);
            }
        }

        expect(clashes).toEqual([]);
    });
});
