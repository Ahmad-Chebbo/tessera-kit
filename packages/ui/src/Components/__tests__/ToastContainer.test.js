import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';
import ToastContainer from '../ToastContainer.vue';
import { useToast } from '../../Composables/useToast.js';
import { i18n } from './helpers';

const page = reactive({ props: {}, flash: {} });

vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

const mountContainer = () => mount(ToastContainer, { global: { plugins: [i18n] } });

describe('ToastContainer', () => {
    beforeEach(() => {
        useToast().clear();
        page.flash = {};
    });

    it('renders toasts with an accessible role per urgency', async () => {
        const wrapper = mountContainer();
        const { success, error } = useToast();

        success('Saved');
        error('Failed');
        await nextTick();

        const items = wrapper.findAll('[data-toast]');
        expect(items).toHaveLength(2);
        expect(items[0].attributes('role')).toBe('status');
        expect(items[1].attributes('role')).toBe('alert');
        expect(items[0].text()).toContain('Saved');
        expect(items[1].text()).toContain('Failed');
    });

    it('shows a title when one is given', async () => {
        const wrapper = mountContainer();

        useToast().add({ type: 'info', title: 'Heads up', message: 'Details' });
        await nextTick();

        expect(wrapper.get('[data-toast]').text()).toContain('Heads up');
        expect(wrapper.get('[data-toast]').text()).toContain('Details');
    });

    it('dismisses a toast with its close button', async () => {
        const wrapper = mountContainer();

        useToast().success('Saved');
        await nextTick();
        await wrapper.get('[data-toast] button[data-close]').trigger('click');

        expect(useToast().toasts.value).toHaveLength(0);
    });

    it('turns flashed session messages into toasts', async () => {
        const wrapper = mountContainer();

        page.flash = { success: 'Branch created successfully.' };
        await nextTick();

        expect(wrapper.get('[data-toast]').text()).toContain('Branch created successfully.');
        expect(wrapper.get('[data-toast]').attributes('data-type')).toBe('success');

        page.flash = { error: 'Cannot delete.' };
        await nextTick();

        expect(wrapper.findAll('[data-toast]').map((toast) => toast.attributes('data-type'))).toEqual(['success', 'error']);
    });

    it('shows a flash that is already present on first render', async () => {
        page.flash = { success: 'Welcome back.' };
        const wrapper = mountContainer();
        await nextTick();

        expect(wrapper.get('[data-toast]').text()).toContain('Welcome back.');
    });

    it('does not toast the auth status key, which pages already show inline', async () => {
        const wrapper = mountContainer();

        page.flash = { status: 'verification-link-sent' };
        await nextTick();

        expect(wrapper.find('[data-toast]').exists()).toBe(false);
    });

    it('raises toasts that already exist into the top layer when it mounts', async () => {
        const originalShow = HTMLElement.prototype.showPopover;
        const originalHide = HTMLElement.prototype.hidePopover;
        const showPopover = vi.fn();
        const hidePopover = vi.fn();
        HTMLElement.prototype.showPopover = showPopover;
        HTMLElement.prototype.hidePopover = hidePopover;

        useToast().success('Already here');
        mountContainer();
        await nextTick();
        await nextTick();

        expect(showPopover).toHaveBeenCalled();

        HTMLElement.prototype.showPopover = originalShow;
        HTMLElement.prototype.hidePopover = originalHide;
    });

    it('keeps a toast open while keyboard focus is inside it, then dismisses it once focus leaves', async () => {
        vi.useFakeTimers();
        const wrapper = mountContainer();

        useToast().success('Pinned', { duration: 1000 });
        await nextTick();

        const toast = wrapper.get('[data-toast]');
        await toast.trigger('mouseenter');
        await toast.trigger('focusin');
        await toast.trigger('mouseleave');

        vi.advanceTimersByTime(5000);
        expect(useToast().toasts.value).toHaveLength(1);

        await toast.trigger('focusout');
        vi.advanceTimersByTime(1001);
        expect(useToast().toasts.value).toHaveLength(0);

        vi.useRealTimers();
    });

    it('keeps a focused toast open after the mouse leaves and resumes when the mouse is gone and focus leaves', async () => {
        vi.useFakeTimers();
        const wrapper = mountContainer();

        useToast().success('Hovered', { duration: 1000 });
        await nextTick();

        const toast = wrapper.get('[data-toast]');
        await toast.trigger('focusin');
        await toast.trigger('mouseenter');
        await toast.trigger('focusout');

        vi.advanceTimersByTime(5000);
        expect(useToast().toasts.value).toHaveLength(1);

        await toast.trigger('mouseleave');
        vi.advanceTimersByTime(1001);
        expect(useToast().toasts.value).toHaveLength(0);

        vi.useRealTimers();
    });
});
