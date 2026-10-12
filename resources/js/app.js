import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import i18n from './i18n';
import { indexPages, resolvePage } from './utils/pages';
import { useToast } from '@ahmad-chebbo/tessera-ui/Composables/useToast';
import { registerInertiaToasts } from '@ahmad-chebbo/tessera-ui/toasts';
import { resolveConfirm } from '@ahmad-chebbo/tessera-ui/Composables/useConfirm';

const pages = indexPages(
    import.meta.glob('./Pages/**/*.vue'),
    import.meta.glob('../../app/Modules/*/Resources/js/Pages/**/*.vue'),
    import.meta.glob('../../vendor/ahmad-chebbo/*/Resources/js/Pages/**/*.vue'),
    import.meta.glob('../../vendor/ahmad-chebbo/*/modules/*/Resources/js/Pages/**/*.vue'),
);

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

registerInertiaToasts(router, useToast(), i18n.global.t);
router.on('navigate', () => resolveConfirm(false));

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePage(pages, name),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#C39B50',
    },
});
