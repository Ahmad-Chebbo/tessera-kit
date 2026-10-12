import { usePage } from '@inertiajs/vue3';

/**
 * Which modules this project has enabled, for core screens that only offer a module's feature when it exists.
 */
export function useModules() {
    const page = usePage();

    return {
        has: (key) => (page.props.enabledModules ?? []).includes(key),
    };
}
