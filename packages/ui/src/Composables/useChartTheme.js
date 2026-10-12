import { onBeforeUnmount, onMounted, ref } from 'vue';
import { readChartTheme } from '../charts/theme.js';

export function useChartTheme() {
    const theme = ref(readChartTheme());
    let observer = null;

    onMounted(() => {
        theme.value = readChartTheme();

        observer = new MutationObserver(() => {
            theme.value = readChartTheme();
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    });

    onBeforeUnmount(() => observer?.disconnect());

    return theme;
}
