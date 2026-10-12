import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { vi } from 'vitest';
import { i18n } from './helpers';

const page = reactive({ props: { branchScope: { canSeeAll: true, branches: [{ id: 1, name: 'Main' }, { id: 2, name: 'Second' }], branchId: 1 } } });

vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

import BranchFilter from '@/Components/BranchFilter.vue';

const mountFilter = (props = {}) => mount(BranchFilter, { props: { modelValue: '', ...props }, global: { plugins: [i18n], stubs: { Select: { name: 'Select', props: ['modelValue'], template: '<div><slot /></div>' } } } });

describe('BranchFilter', () => {
    it('lets people who can see every branch pick one', () => {
        const wrapper = mountFilter();

        expect(wrapper.findComponent({ name: 'Select' }).exists()).toBe(true);
    });

    it('offers all branches first and then each branch', () => {
        const select = mountFilter().findComponent({ name: 'Select' });

        expect(select.text()).toContain('All branches');
        expect(select.text()).toContain('Main');
        expect(select.text()).toContain('Second');
    });

    it('passes the chosen branch up', () => {
        const wrapper = mountFilter();

        wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', 2);

        expect(wrapper.emitted('update:modelValue')[0]).toEqual([2]);
    });

    it('is hidden for people held to their own branch', () => {
        page.props.branchScope = { canSeeAll: false, branches: [{ id: 1, name: 'Main' }], branchId: 1 };

        expect(mountFilter().findComponent({ name: 'Select' }).exists()).toBe(false);
    });

    it('is hidden when the company has a single branch', () => {
        page.props.branchScope = { canSeeAll: true, branches: [{ id: 1, name: 'Main' }], branchId: 1 };

        expect(mountFilter().findComponent({ name: 'Select' }).exists()).toBe(false);
    });
});
