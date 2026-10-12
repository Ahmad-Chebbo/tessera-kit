import { useI18n } from 'vue-i18n';

/**
 * Formats a number of minutes as "7h 30m".
 */
export function useDuration() {
    const { t } = useI18n();

    const formatMinutes = (minutes) => {
        const total = Math.max(0, Math.round(Number(minutes) || 0));
        const hours = Math.floor(total / 60);
        const rest = total % 60;

        if (hours === 0) {
            return t('common.minutesShort', { n: rest });
        }

        return rest === 0 ? `${hours}h` : `${hours}h ${rest}m`;
    };

    return { formatMinutes };
}
