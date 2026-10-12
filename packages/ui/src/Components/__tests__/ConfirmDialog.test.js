import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import ConfirmDialog from '../ConfirmDialog.vue';
import { resolveConfirm, useConfirm } from '../../Composables/useConfirm.js';
import { i18n } from './helpers';

beforeAll(() => {
    HTMLDialogElement.prototype.showModal ||= vi.fn();
    HTMLDialogElement.prototype.close ||= vi.fn();
});

const mountDialog = () => mount(ConfirmDialog, { attachTo: document.body, global: { plugins: [i18n] } });

describe('ConfirmDialog', () => {
    beforeEach(() => {
        resolveConfirm(false);
    });

    it('shows the title and message of the pending confirmation', async () => {
        const wrapper = mountDialog();

        useConfirm()({ title: 'Delete branch?', message: 'This cannot be undone.' });
        await nextTick();
        await nextTick();

        expect(wrapper.text()).toContain('Delete branch?');
        expect(wrapper.text()).toContain('This cannot be undone.');
        wrapper.unmount();
    });

    it('resolves true when the confirm button is pressed', async () => {
        const wrapper = mountDialog();

        const answer = useConfirm()({ message: 'Go ahead?', confirmText: 'Do it' });
        await nextTick();
        await nextTick();

        const confirmButton = wrapper.findAll('button').find((button) => button.text() === 'Do it');
        await confirmButton.trigger('click');

        await expect(answer).resolves.toBe(true);
        wrapper.unmount();
    });

    it('resolves false when cancel is pressed', async () => {
        const wrapper = mountDialog();

        const answer = useConfirm()({ message: 'Go ahead?', cancelText: 'Never mind' });
        await nextTick();
        await nextTick();

        const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Never mind');
        await cancelButton.trigger('click');

        await expect(answer).resolves.toBe(false);
        wrapper.unmount();
    });

    it('resolves false when Escape closes the dialog', async () => {
        const wrapper = mountDialog();

        const answer = useConfirm()({ message: 'Go ahead?' });
        await nextTick();
        await nextTick();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        await expect(answer).resolves.toBe(false);
        wrapper.unmount();
    });

    it('uses a danger button for destructive confirmations', async () => {
        const wrapper = mountDialog();

        useConfirm().delete('Remove it?');
        await nextTick();
        await nextTick();

        const confirmButton = wrapper.findAll('button').find((button) => button.text() === 'Delete');
        expect(confirmButton.classes()).toContain('bg-danger');
        wrapper.unmount();
    });

    it('uses the gold primary button for ordinary confirmations', async () => {
        const wrapper = mountDialog();

        useConfirm()({ message: 'Receive stock?', confirmText: 'Receive' });
        await nextTick();
        await nextTick();

        const confirmButton = wrapper.findAll('button').find((button) => button.text() === 'Receive');
        expect(confirmButton.classes()).toContain('bg-gold');
        wrapper.unmount();
    });

    it('labels the dialog for assistive technology and uses an alert role for destructive actions', async () => {
        const wrapper = mountDialog();

        useConfirm().delete('Remove it?');
        await nextTick();
        await nextTick();

        const dialog = wrapper.get('[role="alertdialog"]');
        const title = document.getElementById(dialog.attributes('aria-labelledby'));
        const description = document.getElementById(dialog.attributes('aria-describedby'));

        expect(title.textContent).toContain('Delete this item?');
        expect(description.textContent).toContain('Remove it?');
        wrapper.unmount();
    });

    it('uses a plain dialog role for ordinary confirmations', async () => {
        const wrapper = mountDialog();

        useConfirm()({ message: 'Receive stock?' });
        await nextTick();
        await nextTick();

        expect(wrapper.find('[role="alertdialog"]').exists()).toBe(false);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
        wrapper.unmount();
    });
});
