import { createTesseraI18n, setLocale } from '@tessera/tessera-ui/i18n';
import messages from './messages';

const i18n = createTesseraI18n(messages);

export { setLocale };
export default i18n;
