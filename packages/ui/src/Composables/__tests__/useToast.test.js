import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useToast } from '../useToast.js';

describe('useToast', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        useToast().clear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('adds a toast with sensible defaults per type', () => {
        const { toasts, success, error } = useToast();

        success('Saved');
        error('Failed');

        expect(toasts.value.map((toast) => [toast.type, toast.message])).toEqual([
            ['success', 'Saved'],
            ['error', 'Failed'],
        ]);
        expect(toasts.value[1].duration).toBeGreaterThan(toasts.value[0].duration);
    });

    it('offers warning and info helpers and a generic add', () => {
        const { toasts, warning, info, add } = useToast();

        warning('Careful');
        info('FYI');
        add({ type: 'success', title: 'Done', message: 'All good', duration: 1000 });

        expect(toasts.value.map((toast) => toast.type)).toEqual(['warning', 'info', 'success']);
        expect(toasts.value[2]).toMatchObject({ title: 'Done', message: 'All good', duration: 1000 });
    });

    it('dismisses automatically after its duration', () => {
        const { toasts, success } = useToast();

        success('Saved', { duration: 2000 });
        vi.advanceTimersByTime(1999);
        expect(toasts.value).toHaveLength(1);

        vi.advanceTimersByTime(2);
        expect(toasts.value).toHaveLength(0);
    });

    it('stays until dismissed when the duration is 0', () => {
        const { toasts, error } = useToast();

        error('Sticky', { duration: 0 });
        vi.advanceTimersByTime(60_000);

        expect(toasts.value).toHaveLength(1);
    });

    it('pauses and resumes the countdown', () => {
        const { toasts, success, pause, resume } = useToast();

        const id = success('Saved', { duration: 3000 });
        vi.advanceTimersByTime(1000);
        pause(id);
        vi.advanceTimersByTime(10_000);
        expect(toasts.value).toHaveLength(1);

        resume(id);
        vi.advanceTimersByTime(1999);
        expect(toasts.value).toHaveLength(1);
        vi.advanceTimersByTime(2);
        expect(toasts.value).toHaveLength(0);
    });

    it('refreshes an identical visible toast instead of stacking duplicates', () => {
        const { toasts, success } = useToast();

        const first = success('Saved', { duration: 3000 });
        vi.advanceTimersByTime(2000);
        const second = success('Saved', { duration: 3000 });

        expect(second).toBe(first);
        expect(toasts.value).toHaveLength(1);

        vi.advanceTimersByTime(2000);
        expect(toasts.value).toHaveLength(1);
    });

    it('keeps at most five toasts, dropping the oldest', () => {
        const { toasts, info } = useToast();

        for (let i = 1; i <= 7; i++) {
            info(`Message ${i}`);
        }

        expect(toasts.value).toHaveLength(5);
        expect(toasts.value[0].message).toBe('Message 3');
    });

    it('dismisses one toast or clears all', () => {
        const { toasts, info, dismiss, clear } = useToast();

        const first = info('One');
        info('Two');
        dismiss(first);
        expect(toasts.value.map((toast) => toast.message)).toEqual(['Two']);

        clear();
        expect(toasts.value).toHaveLength(0);
    });
});
