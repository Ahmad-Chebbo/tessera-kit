import { createI18n } from 'vue-i18n';
import uiMessages from './locales';

let instance = null;

function savedLocale() {
    try {
        return localStorage.getItem('locale') || 'en';
    } catch {
        return 'en';
    }
}

function applyDirection(locale) {
    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.lang = locale;
}

/**
 * Creates the i18n instance the components share. Pass the translations of the whole app (the UI's own texts are
 * already part of `uiMessages`, merge them in first); the instance is remembered so components can reach it outside
 * of setup().
 */
export function createTesseraI18n(messages = uiMessages, { fallbackLocale = 'en' } = {}) {
    const locale = savedLocale();

    instance = createI18n({ legacy: false, locale, fallbackLocale, messages });
    applyDirection(locale);

    return instance;
}

export function getI18n() {
    return instance ?? createTesseraI18n();
}

export function setLocale(locale) {
    getI18n().global.locale.value = locale;

    try {
        localStorage.setItem('locale', locale);
    } catch {
        // Persisting is optional.
    }

    applyDirection(locale);
}

export { uiMessages };
