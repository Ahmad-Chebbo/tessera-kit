import { createTesseraI18n, setLocale } from '@ahmad-chebbo/tessera-ui/i18n';
import messages from './messages';

const i18n = createTesseraI18n(messages);

export { setLocale };
export default i18n;
