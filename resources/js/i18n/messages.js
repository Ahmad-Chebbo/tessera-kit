import en from './locales/en.json';
import ar from './locales/ar.json';
import uiMessages from '@ahmad-chebbo/tessera-ui/locales';

const moduleFiles = {
    ...import.meta.glob('../../../app/Modules/*/Resources/lang/*.json', { eager: true, import: 'default' }),
    ...import.meta.glob('../../../vendor/ahmad-chebbo/*/Resources/lang/*.json', { eager: true, import: 'default' }),
    ...import.meta.glob('../../../vendor/ahmad-chebbo/*/modules/*/Resources/lang/*.json', { eager: true, import: 'default' }),
};

/**
 * Merges the translations of two sources locale by locale.
 */
function mergeLocales(first, second) {
    return Object.fromEntries([...new Set([...Object.keys(first), ...Object.keys(second)])].map((locale) => [locale, mergeMessages(first[locale] ?? {}, second[locale] ?? {})]));
}

/**
 * Merges translation trees; later trees add to earlier ones and win on a clash of leaf values.
 */
export function mergeMessages(...trees) {
    const merged = {};

    for (const tree of trees) {
        for (const [key, value] of Object.entries(tree)) {
            merged[key] = value && typeof value === 'object' && !Array.isArray(value) && typeof merged[key] === 'object'
                ? mergeMessages(merged[key], value)
                : value;
        }
    }

    return merged;
}

/**
 * Every leaf of a translation tree as a dotted path.
 */
export function leafPaths(tree, prefix = '') {
    return Object.entries(tree).flatMap(([key, value]) =>
        value && typeof value === 'object' && !Array.isArray(value)
            ? leafPaths(value, `${prefix}${key}.`)
            : [`${prefix}${key}`],
    );
}

/**
 * Core translations plus the translations each module keeps in its own Resources/lang folder.
 */
export function collectMessages(files = moduleFiles, core = mergeLocales(uiMessages, { en, ar })) {
    const byLocale = { ...core };

    for (const [path, tree] of Object.entries(files).sort(([a], [b]) => a.localeCompare(b))) {
        const locale = path.match(/\/([^/]+)\.json$/)[1];
        byLocale[locale] = mergeMessages(byLocale[locale] ?? {}, tree);
    }

    return byLocale;
}

export const coreMessages = mergeLocales(uiMessages, { en, ar });
export const moduleMessageFiles = moduleFiles;
export default collectMessages();
