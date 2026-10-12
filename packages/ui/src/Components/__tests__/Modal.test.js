import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import Modal from '../Modal.vue';

describe('Modal', () => {
    let showModal;
    let close;

    beforeEach(() => {
        vi.useFakeTimers();
        showModal = vi.fn(function () {
            this.setAttribute('open', '');
        });
        close = vi.fn(function () {
            this.removeAttribute('open');
        });
        HTMLDialogElement.prototype.showModal = showModal;
        HTMLDialogElement.prototype.close = close;
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('opens the native dialog when it mounts already shown', async () => {
        const wrapper = mount(Modal, { props: { show: true }, slots: { default: '<p>Hello</p>' }, attachTo: document.body });
        await nextTick();
        await nextTick();

        expect(showModal).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain('Hello');
        wrapper.unmount();
    });

    it('does not close a dialog that was re-opened before the close delay finished', async () => {
        const wrapper = mount(Modal, { props: { show: false }, slots: { default: '<p>Hello</p>' }, attachTo: document.body });

        await wrapper.setProps({ show: true });
        await wrapper.setProps({ show: false });
        vi.advanceTimersByTime(100);
        await wrapper.setProps({ show: true });
        vi.advanceTimersByTime(500);
        await nextTick();

        expect(close).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Hello');
        wrapper.unmount();
    });

    it('still closes after the delay when it stays hidden', async () => {
        const wrapper = mount(Modal, { props: { show: true }, slots: { default: '<p>Hello</p>' }, attachTo: document.body });
        await nextTick();

        await wrapper.setProps({ show: false });
        vi.advanceTimersByTime(250);
        await nextTick();

        expect(close).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).not.toContain('Hello');
        wrapper.unmount();
    });

    it('paints the panel above the click-to-close scrim so clicks on it do not dismiss the modal', async () => {
        const wrapper = mount(Modal, { props: { show: true }, slots: { default: '<button id="inside">Inside</button>' }, attachTo: document.body });
        await nextTick();
        await nextTick();

        const panel = wrapper.get('#inside').element.parentElement;
        const scrim = wrapper.get('.fixed.inset-0 > .absolute').element.parentElement;

        expect(panel.classList.contains('relative')).toBe(true);
        expect(panel.className).not.toMatch(/(^|\s)transform(\s|$)/);
        expect(scrim.contains(panel)).toBe(false);

        await wrapper.get('#inside').trigger('click');
        expect(wrapper.emitted('close')).toBeUndefined();

        wrapper.unmount();
    });
});
