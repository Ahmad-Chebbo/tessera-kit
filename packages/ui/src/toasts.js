export function registerInertiaToasts(router, toast, t) {
    const stops = [
        router.on('error', () => toast.warning(t('toast.validation'))),
        router.on('invalid', () => toast.error(t('toast.serverError'))),
        router.on('exception', () => toast.error(t('toast.networkError'))),
    ];

    return () => stops.forEach((stop) => stop());
}
