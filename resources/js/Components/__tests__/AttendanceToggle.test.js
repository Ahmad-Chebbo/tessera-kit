import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { reactive } from 'vue';

const page = reactive({ props: { attendance: null, auth: { permissions: ['record attendance'] } } });
const post = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: { post: (...args) => post(...args) },
}));

import AttendanceToggle from '../AttendanceToggle.vue';

const i18n = createI18n({
    legacy: false,
    locale: 'en',
    messages: { en: { attendance: { checkIn: 'Check in', checkOut: 'Check out', checkedInSince: 'In since {time}', notCheckedIn: 'Not checked in' } } },
});

const mountToggle = () => mount(AttendanceToggle, { global: { plugins: [i18n] } });

describe('AttendanceToggle', () => {
    beforeEach(() => {
        post.mockClear();
        globalThis.route = (name) => `/${name}`;
        page.props.attendance = null;
        page.props.auth.permissions = ['record attendance'];
    });

    it('offers check in when nobody is checked in', async () => {
        const wrapper = mountToggle();

        expect(wrapper.text()).toContain('Check in');
        await wrapper.trigger('click');
        expect(post).toHaveBeenCalledWith('/attendance.check-in', {}, { preserveScroll: true });
    });

    it('offers check out while checked in', async () => {
        page.props.attendance = { id: 1, check_in_at: '2026-10-14T09:00:00Z' };
        const wrapper = mountToggle();

        expect(wrapper.text()).toContain('Check out');
        await wrapper.trigger('click');
        expect(post).toHaveBeenCalledWith('/attendance.check-out', {}, { preserveScroll: true });
    });

    it('renders nothing without the permission', () => {
        page.props.auth.permissions = [];

        expect(mountToggle().find('button').exists()).toBe(false);
    });
});
