import { ref } from 'vue';

const MODES = ['table', 'grid'];

export function useViewMode(key, defaultMode = 'table') {
    const storageKey = `view:${key}`;

    function read() {
        try {
            const stored = localStorage.getItem(storageKey);

            return MODES.includes(stored) ? stored : defaultMode;
        } catch {
            return defaultMode;
        }
    }

    const mode = ref(read());

    function setMode(value) {
        if (!MODES.includes(value)) {
            return;
        }

        mode.value = value;

        try {
            localStorage.setItem(storageKey, value);
        } catch {
            // Storage can be blocked; the choice still applies for this visit.
        }
    }

    return { mode, setMode };
}
