import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

/**
 * The sidebar entries the server built from the enabled modules, with their labels translated.
 * Standalone entries come as { name, href, icon, active }; sections as { section, items: [...] }.
 */
export function useNavigation() {
    const { t } = useI18n();
    const page = usePage();

    const entry = (item) => ({ name: t(item.label), href: item.href, icon: item.icon, active: item.active });

    return computed(() =>
        (page.props.navigation ?? []).map((item) =>
            item.section
                ? { section: item.section, items: item.items.map(entry) }
                : entry(item),
        ),
    );
}
