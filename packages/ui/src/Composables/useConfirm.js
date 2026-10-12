import { nextTick, reactive } from 'vue';
import { getI18n } from '../i18n.js';

export const confirmState = reactive({
    open: false,
    title: '',
    message: '',
    confirmText: '',
    cancelText: '',
    variant: 'default',
    resolve: null,
});

export function resolveConfirm(result) {
    const resolve = confirmState.resolve;

    confirmState.open = false;
    confirmState.resolve = null;
    resolve?.(result);
}

function open(options) {
    const { t } = getI18n().global;

    return new Promise((resolve) => {
        Object.assign(confirmState, {
            title: options.title ?? t('confirm.title'),
            message: options.message ?? '',
            confirmText: options.confirmText ?? t('common.confirm'),
            cancelText: options.cancelText ?? t('common.cancel'),
            variant: options.variant ?? 'default',
            resolve,
            open: true,
        });
    });
}

function confirm(options = {}) {
    if (!confirmState.resolve) {
        return open(options);
    }

    resolveConfirm(false);

    return nextTick().then(() => open(options));
}

confirm.delete = (message) => {
    const { t } = getI18n().global;

    return confirm({
        variant: 'danger',
        title: t('confirm.deleteTitle'),
        message: message ?? t('confirm.cannotUndo'),
        confirmText: t('common.delete'),
    });
};

export function useConfirm() {
    return confirm;
}
