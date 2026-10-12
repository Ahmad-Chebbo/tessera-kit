import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import FileUpload from '../FileUpload.vue';
import { i18n } from './helpers';

const mountUpload = (props = {}) =>
    mount(FileUpload, { props, global: { plugins: [i18n] } });

const pickFile = async (wrapper, file) => {
    const input = wrapper.get('input[type="file"]');
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true });
    await input.trigger('change');
};

const png = () => new File(['x'], 'photo.png', { type: 'image/png' });

describe('FileUpload', () => {
    beforeEach(() => {
        URL.createObjectURL = vi.fn(() => 'blob:preview');
        URL.revokeObjectURL = vi.fn();
    });

    it('emits the chosen file from the file input', async () => {
        const wrapper = mountUpload();
        const file = png();

        await pickFile(wrapper, file);

        expect(wrapper.emitted('update:modelValue')[0]).toEqual([file]);
        expect(wrapper.emitted('change')[0]).toEqual([file]);
    });

    it('emits the first dropped file', async () => {
        const wrapper = mountUpload();
        const file = png();

        await wrapper.get('[data-dropzone]').trigger('drop', { dataTransfer: { files: [file] } });

        expect(wrapper.emitted('update:modelValue')[0]).toEqual([file]);
    });

    it('rejects a dropped file that does not match accept and explains why', async () => {
        const wrapper = mountUpload({ accept: 'image/*' });
        const text = new File(['x'], 'notes.txt', { type: 'text/plain' });

        await wrapper.get('[data-dropzone]').trigger('drop', { dataTransfer: { files: [text] } });

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect(wrapper.text()).toContain('not an accepted file type');
    });

    it('rejects a file over the size limit', async () => {
        const wrapper = mountUpload({ maxSizeMb: 1 });
        const big = new File([new Uint8Array(2 * 1024 * 1024)], 'big.png', { type: 'image/png' });

        await pickFile(wrapper, big);

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect(wrapper.text()).toContain('larger than 1 MB');
    });

    it('shows an image preview for a chosen image and for an existing url', async () => {
        const chosen = mountUpload({ modelValue: png() });
        expect(chosen.get('img').attributes('src')).toBe('blob:preview');

        const existing = mountUpload({ previewUrl: '/storage/p.jpg' });
        expect(existing.get('img').attributes('src')).toBe('/storage/p.jpg');
    });

    it('shows the file name for non-image files', () => {
        const wrapper = mountUpload({
            accept: '*/*',
            modelValue: new File(['x'], 'report.pdf', { type: 'application/pdf' }),
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('report.pdf');
    });

    it('clears the selection with the remove button', async () => {
        const wrapper = mountUpload({ modelValue: png() });

        await wrapper.get('button[data-remove]').trigger('click');

        expect(wrapper.emitted('update:modelValue')[0]).toEqual([null]);
        expect(wrapper.emitted('remove')).toHaveLength(1);
    });

    it('shows an error passed in by the form', () => {
        const wrapper = mountUpload({ error: 'The image failed to upload.' });

        expect(wrapper.text()).toContain('The image failed to upload.');
    });

    it('limits the native file picker with the accept prop', () => {
        const wrapper = mountUpload({ accept: 'image/png' });

        expect(wrapper.get('input[type="file"]').attributes('accept')).toBe('image/png');
    });
});
