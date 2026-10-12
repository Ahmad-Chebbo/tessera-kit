import { createI18n } from 'vue-i18n';
import messages from '@/i18n/messages';

export const i18n = createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    messages: { en: messages.en },
});
