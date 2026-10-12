import { ref } from 'vue';

const STORAGE_KEY = 'theme';

function readStoredTheme() {
    try {
        return localStorage.getItem(STORAGE_KEY) === 'dark' ? 'dark' : 'light';
    } catch {
        return 'light';
    }
}

const theme = ref(readStoredTheme());

function applyTheme(value) {
    document.documentElement.setAttribute('data-theme', value);
}

applyTheme(theme.value);

export function useTheme() {
    function setTheme(value) {
        theme.value = value === 'dark' ? 'dark' : 'light';
        applyTheme(theme.value);

        try {
            localStorage.setItem(STORAGE_KEY, theme.value);
        } catch {
            // Storage can be blocked (private window); the theme still applies for this visit.
        }
    }

    function toggleTheme() {
        setTheme(theme.value === 'dark' ? 'light' : 'dark');
    }

    return { theme, setTheme, toggleTheme };
}
