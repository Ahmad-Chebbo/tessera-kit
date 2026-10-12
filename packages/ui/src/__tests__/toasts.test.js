import { beforeEach, describe, expect, it, vi } from 'vitest';
import { registerInertiaToasts } from '../toasts.js';
import { useToast } from '../Composables/useToast.js';

const t = (key) => key;

const fakeRouter = () => {
    const handlers = {};

    return {
        handlers,
        on: vi.fn((event, callback) => {
            handlers[event] = callback;

            return () => delete handlers[event];
        }),
    };
};

describe('registerInertiaToasts', () => {
    beforeEach(() => {
        useToast().clear();
    });

    it('warns once when a submission comes back with validation errors', () => {
        const router = fakeRouter();
        registerInertiaToasts(router, useToast(), t);

        router.handlers.error({ detail: { errors: { name: 'The name field is required.' } } });

        expect(useToast().toasts.value).toHaveLength(1);
        expect(useToast().toasts.value[0]).toMatchObject({ type: 'warning', message: 'toast.validation' });
    });

    it('reports a server error page', () => {
        const router = fakeRouter();
        registerInertiaToasts(router, useToast(), t);

        router.handlers.invalid({ detail: { response: { status: 500 } } });

        expect(useToast().toasts.value[0]).toMatchObject({ type: 'error', message: 'toast.serverError' });
    });

    it('reports a network failure', () => {
        const router = fakeRouter();
        registerInertiaToasts(router, useToast(), t);

        router.handlers.exception({ detail: { exception: new Error('Network Error') } });

        expect(useToast().toasts.value[0]).toMatchObject({ type: 'error', message: 'toast.networkError' });
    });

    it('returns a function that unsubscribes every listener', () => {
        const router = fakeRouter();
        const stop = registerInertiaToasts(router, useToast(), t);

        expect(Object.keys(router.handlers).sort()).toEqual(['error', 'exception', 'invalid']);

        stop();
        expect(Object.keys(router.handlers)).toEqual([]);
    });
});
