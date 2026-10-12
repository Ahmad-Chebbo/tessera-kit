import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { i18n } from './helpers';

const page = reactive({ props: { auth: { permissions: ['export reports'] } } });
const post = vi.fn();
const toast = { success: vi.fn(), error: vi.fn(), info: vi.fn() };

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));
vi.mock('axios', () => ({ default: { post: (...args) => post(...args) } }));
vi.mock('@ahmad-chebbo/tessera-ui/Composables/useToast', () => ({ useToast: () => toast }));

import ExportMenu from '@/Components/ExportMenu.vue';

const mountMenu = (props = {}) =>
    mount(ExportMenu, { props: { report: 'sales', filters: { date_from: '2026-10-01', date_to: '2026-10-14', search: '', category_id: null }, ...props }, global: { plugins: [i18n], mocks: { route: (name) => `/${name}` } } });

const choose = async (wrapper, format) => {
    await wrapper.find('button[aria-haspopup="menu"]').trigger('click');
    await wrapper.find(`[data-format="${format}"]`).trigger('click');
    await flushPromises();
};

describe('ExportMenu', () => {
    beforeEach(() => {
        post.mockReset();
        Object.values(toast).forEach((fn) => fn.mockReset());
        globalThis.route = (name, params) => `/${name}${params ? `/${params}` : ''}`;
        page.props.auth.permissions = ['export reports'];
        i18n.global.locale.value = 'en';
        Object.defineProperty(window, 'location', { value: { assign: vi.fn() }, writable: true });
    });

    it('renders nothing without the export permission', () => {
        page.props.auth.permissions = [];

        expect(mountMenu().find('button').exists()).toBe(false);
    });

    it('offers Excel and PDF and a link to the exports page', async () => {
        const wrapper = mountMenu();

        await wrapper.find('button[aria-haspopup="menu"]').trigger('click');

        expect(wrapper.findAll('[role="menuitem"]').map((item) => item.text())).toEqual(['Excel (.xlsx)', 'PDF', 'My exports']);
        expect(wrapper.find('a').attributes('href')).toBe('/exports.index');
    });

    it('sends the report, format, locale and only the filters that have a value', async () => {
        post.mockResolvedValue({ data: { status: 'ready', download_url: '/exports/5/download' } });

        await choose(mountMenu(), 'xlsx');

        expect(post).toHaveBeenCalledWith('/exports.store', {
            report: 'sales', format: 'xlsx', locale: 'en', filters: { date_from: '2026-10-01', date_to: '2026-10-14' },
        });
    });

    it('sends the current interface language', async () => {
        i18n.global.locale.value = 'ar';
        post.mockResolvedValue({ data: { status: 'ready', download_url: '/d' } });

        await choose(mountMenu(), 'pdf');

        expect(post.mock.calls[0][1]).toMatchObject({ format: 'pdf', locale: 'ar' });
    });

    it('downloads a finished export straight away', async () => {
        post.mockResolvedValue({ data: { status: 'ready', download_url: '/exports/5/download' } });

        await choose(mountMenu(), 'xlsx');

        expect(window.location.assign).toHaveBeenCalledWith('/exports/5/download');
        expect(toast.success).toHaveBeenCalled();
    });

    it('tells the user a big export continues in the background', async () => {
        post.mockResolvedValue({ data: { status: 'queued', download_url: null } });

        await choose(mountMenu(), 'xlsx');

        expect(window.location.assign).not.toHaveBeenCalled();
        expect(toast.info).toHaveBeenCalledWith(expect.stringContaining('background'));
    });

    it('shows the server validation message', async () => {
        post.mockRejectedValue({ response: { data: { errors: { format: ['Too large for a PDF.'] } } } });

        await choose(mountMenu(), 'pdf');

        expect(toast.error).toHaveBeenCalledWith('Too large for a PDF.');
    });

    it('disables the button while an export is being prepared', async () => {
        let finish;
        post.mockReturnValue(new Promise((resolve) => (finish = resolve)));
        const wrapper = mountMenu();

        await wrapper.find('button[aria-haspopup="menu"]').trigger('click');
        await wrapper.find('[data-format="xlsx"]').trigger('click');

        expect(wrapper.find('button[aria-haspopup="menu"]').attributes('disabled')).toBeDefined();

        finish({ data: { status: 'queued' } });
        await flushPromises();
        expect(wrapper.find('button[aria-haspopup="menu"]').attributes('disabled')).toBeUndefined();
    });
});
