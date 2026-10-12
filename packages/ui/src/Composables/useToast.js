import { ref } from 'vue';

const MAX_TOASTS = 5;
const DEFAULT_DURATION = { success: 4000, info: 5000, warning: 6000, error: 8000 };

const toasts = ref([]);
const timers = new Map();
let nextId = 1;

function stopTimer(id) {
    const timer = timers.get(id);

    if (timer?.handle) {
        clearTimeout(timer.handle);
    }
}

function startTimer(id, milliseconds) {
    stopTimer(id);

    if (milliseconds <= 0) {
        timers.delete(id);

        return;
    }

    timers.set(id, {
        handle: setTimeout(() => dismiss(id), milliseconds),
        remaining: milliseconds,
        startedAt: Date.now(),
    });
}

function dismiss(id) {
    stopTimer(id);
    timers.delete(id);
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function pause(id) {
    const timer = timers.get(id);

    if (!timer?.handle) {
        return;
    }

    clearTimeout(timer.handle);
    timer.handle = null;
    timer.remaining -= Date.now() - timer.startedAt;
}

function resume(id) {
    const timer = timers.get(id);

    if (timer && !timer.handle) {
        startTimer(id, Math.max(timer.remaining, 1));
    }
}

function add({ type = 'info', message, title = null, duration } = {}) {
    const lifetime = duration ?? DEFAULT_DURATION[type] ?? DEFAULT_DURATION.info;
    const existing = toasts.value.find(
        (toast) => toast.type === type && toast.message === message && toast.title === title,
    );

    if (existing) {
        existing.duration = lifetime;
        startTimer(existing.id, lifetime);

        return existing.id;
    }

    const id = nextId++;
    toasts.value = [...toasts.value, { id, type, title, message, duration: lifetime }];
    startTimer(id, lifetime);

    while (toasts.value.length > MAX_TOASTS) {
        dismiss(toasts.value[0].id);
    }

    return id;
}

function clear() {
    [...timers.keys()].forEach(stopTimer);
    timers.clear();
    toasts.value = [];
}

export function useToast() {
    return {
        toasts,
        add,
        dismiss,
        pause,
        resume,
        clear,
        success: (message, options = {}) => add({ ...options, type: 'success', message }),
        error: (message, options = {}) => add({ ...options, type: 'error', message }),
        warning: (message, options = {}) => add({ ...options, type: 'warning', message }),
        info: (message, options = {}) => add({ ...options, type: 'info', message }),
    };
}
