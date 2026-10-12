import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

export function useCurrency() {
    const { locale } = useI18n();
    const page = usePage();
    const curr = page.props.currency || 'USD';
    

    const formatCurrency = (amount, currency = curr) => {
        // const localeCode = locale.value === 'ar' ? 'ar-SA' : 'en-US';
        const localeCode = 'en-US';

        try {
            return new Intl.NumberFormat(localeCode, {
                style: 'currency',
                currency: currency,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(amount);
        } catch (e) {
            // Fallback if currency code is not supported
            return `${currency} ${amount.toFixed(2)}`;
        }
    };

    const formatNumber = (amount, decimals = 2) => {
        // const localeCode = locale.value === 'ar' ? 'ar-SA' : 'en-US';
        const localeCode = 'en-US';

        return new Intl.NumberFormat(localeCode, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(amount);
    };

    return {
        formatCurrency,
        formatNumber,
    };
}
