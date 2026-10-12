import { beforeEach, describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import { confirmState, resolveConfirm, useConfirm } from '../useConfirm.js';

describe('useConfirm', () => {
    beforeEach(() => {
        resolveConfirm(false);
    });

    it('opens with the given options and resolves true when accepted', async () => {
        const confirm = useConfirm();

        const answer = confirm({ title: 'Delete?', message: 'Gone forever', variant: 'danger' });

        expect(confirmState.open).toBe(true);
        expect(confirmState).toMatchObject({ title: 'Delete?', message: 'Gone forever', variant: 'danger' });

        resolveConfirm(true);

        await expect(answer).resolves.toBe(true);
        expect(confirmState.open).toBe(false);
    });

    it('resolves false when cancelled', async () => {
        const answer = useConfirm()({ message: 'Sure?' });

        resolveConfirm(false);

        await expect(answer).resolves.toBe(false);
        expect(confirmState.open).toBe(false);
    });

    it('cancels a pending confirmation when a new one is requested', async () => {
        const confirm = useConfirm();

        const first = confirm({ message: 'First' });
        const second = confirm({ message: 'Second' });

        await expect(first).resolves.toBe(false);
        await nextTick();
        await nextTick();
        expect(confirmState.message).toBe('Second');

        resolveConfirm(true);
        await expect(second).resolves.toBe(true);
    });

    it('does nothing when resolved with no pending confirmation', () => {
        expect(() => resolveConfirm(true)).not.toThrow();
        expect(confirmState.open).toBe(false);
    });

    it('offers a delete shortcut with the danger variant', async () => {
        const answer = useConfirm().delete('Remove this branch?');

        expect(confirmState).toMatchObject({ variant: 'danger', message: 'Remove this branch?', open: true });

        resolveConfirm(true);
        await expect(answer).resolves.toBe(true);
    });

    it('falls back to a generic warning when the delete shortcut has no message', () => {
        useConfirm().delete();

        expect(confirmState.message).toBeTruthy();
        expect(confirmState.title).toBeTruthy();
        expect(confirmState.confirmText).toBeTruthy();
    });

    it('re-opens cleanly when a new confirmation is requested while one is open', async () => {
        const confirm = useConfirm();

        const first = confirm({ message: 'First' });
        const second = confirm({ message: 'Second' });

        await expect(first).resolves.toBe(false);
        await nextTick();
        await nextTick();

        expect(confirmState.open).toBe(true);
        expect(confirmState.message).toBe('Second');

        resolveConfirm(true);
        await expect(second).resolves.toBe(true);
    });
});
