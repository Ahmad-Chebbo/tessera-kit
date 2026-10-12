import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import DatePicker from '../DatePicker.vue';
import { i18n } from './helpers';

const mountPicker = (props = {}) =>
    mount(DatePicker, {
        props: { modelValue: '', ...props },
        attachTo: document.body,
        global: { plugins: [i18n], stubs: { teleport: true } },
    });

const open = async (wrapper) => {
    await wrapper.get('button[data-trigger]').trigger('click');
    await nextTick();
};

const day = (wrapper, iso) => wrapper.get(`button[data-day="${iso}"]`);

describe('DatePicker', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date(2026, 4, 15, 12, 0));
    });

    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('shows the placeholder when empty and the formatted date when set', () => {
        expect(mountPicker({ placeholder: 'Pick a day' }).get('button[data-trigger]').text()).toContain('Pick a day');
        expect(mountPicker({ modelValue: '2026-05-15' }).get('button[data-trigger]').text()).toContain('May 15, 2026');
    });

    it('opens on click and shows the month of the value, or today when empty', async () => {
        const withValue = mountPicker({ modelValue: '2026-03-10' });
        expect(withValue.find('[data-panel]').exists()).toBe(false);
        await open(withValue);
        expect(withValue.get('[data-title]').text()).toBe('March 2026');

        const empty = mountPicker();
        await open(empty);
        expect(empty.get('[data-title]').text()).toBe('May 2026');
    });

    it('lays out six weeks starting on Monday, including neighbouring months', async () => {
        const wrapper = mountPicker({ modelValue: '2026-05-15' });
        await open(wrapper);

        expect(wrapper.findAll('button[data-day]')).toHaveLength(42);
        expect(wrapper.findAll('button[data-day]')[0].attributes('data-day')).toBe('2026-04-27');
        expect(wrapper.findAll('[data-weekday]')[0].text()).toBe('Mon');
    });

    it('emits the picked date as ISO and closes', async () => {
        const wrapper = mountPicker({ modelValue: '2026-05-15' });
        await open(wrapper);

        await day(wrapper, '2026-05-20').trigger('click');

        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['2026-05-20']);
        expect(wrapper.emitted('change')[0]).toEqual(['2026-05-20']);
        expect(wrapper.find('[data-panel]').exists()).toBe(false);
    });

    it('marks today and the selected day', async () => {
        const wrapper = mountPicker({ modelValue: '2026-05-20' });
        await open(wrapper);

        expect(day(wrapper, '2026-05-15').attributes('data-today')).toBeDefined();
        expect(day(wrapper, '2026-05-20').attributes('aria-pressed')).toBe('true');
        expect(day(wrapper, '2026-05-21').attributes('aria-pressed')).toBe('false');
    });

    it('navigates by month and year', async () => {
        const wrapper = mountPicker({ modelValue: '2026-05-15' });
        await open(wrapper);

        await wrapper.get('button[data-nav="next-month"]').trigger('click');
        expect(wrapper.get('[data-title]').text()).toBe('June 2026');

        await wrapper.get('button[data-nav="prev-year"]').trigger('click');
        expect(wrapper.get('[data-title]').text()).toBe('June 2025');

        await wrapper.get('button[data-nav="prev-month"]').trigger('click');
        expect(wrapper.get('[data-title]').text()).toBe('May 2025');

        await wrapper.get('button[data-nav="next-year"]').trigger('click');
        expect(wrapper.get('[data-title]').text()).toBe('May 2026');
    });

    it('disables days outside min and max and ignores clicks on them', async () => {
        const wrapper = mountPicker({ modelValue: '2026-05-15', min: '2026-05-10', max: '2026-05-20' });
        await open(wrapper);

        expect(day(wrapper, '2026-05-09').attributes('disabled')).toBeDefined();
        expect(day(wrapper, '2026-05-10').attributes('disabled')).toBeUndefined();
        expect(day(wrapper, '2026-05-21').attributes('disabled')).toBeDefined();

        await day(wrapper, '2026-05-21').trigger('click');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('clears from the trigger and from the panel', async () => {
        const wrapper = mountPicker({ modelValue: '2026-05-15' });

        await wrapper.get('button[data-clear-trigger]').trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['']);

        await open(wrapper);
        await wrapper.get('button[data-clear]').trigger('click');
        expect(wrapper.emitted('update:modelValue')[1]).toEqual(['']);
    });

    it('hides the clear control when there is nothing to clear or clearing is off', () => {
        expect(mountPicker().find('button[data-clear-trigger]').exists()).toBe(false);
        expect(mountPicker({ modelValue: '2026-05-15', clearable: false }).find('button[data-clear-trigger]').exists()).toBe(false);
    });

    it('jumps to today with the Today button', async () => {
        const wrapper = mountPicker({ modelValue: '2026-01-01' });
        await open(wrapper);

        await wrapper.get('button[data-today-btn]').trigger('click');

        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['2026-05-15']);
    });

    it('does not offer Today when today is outside the allowed range', async () => {
        const wrapper = mountPicker({ modelValue: '2026-01-01', max: '2026-02-01' });
        await open(wrapper);

        expect(wrapper.get('button[data-today-btn]').attributes('disabled')).toBeDefined();
    });

    it('supports keyboard selection: arrows move, Enter picks, PageDown changes month, Escape closes', async () => {
        const wrapper = mountPicker({ modelValue: '2026-05-15' });
        const trigger = wrapper.get('button[data-trigger]');

        await trigger.trigger('keydown', { key: 'ArrowDown' });
        await nextTick();
        expect(wrapper.find('[data-panel]').exists()).toBe(true);

        const grid = wrapper.get('[data-grid]');
        await grid.trigger('keydown', { key: 'ArrowRight' });
        await grid.trigger('keydown', { key: 'ArrowDown' });
        await grid.trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['2026-05-23']);

        await open(wrapper);
        await wrapper.get('[data-grid]').trigger('keydown', { key: 'PageDown' });
        expect(wrapper.get('[data-title]').text()).toBe('June 2026');

        await wrapper.get('[data-grid]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('[data-panel]').exists()).toBe(false);
        expect(wrapper.emitted('update:modelValue')).toHaveLength(1);
    });

    it('does not open when disabled', async () => {
        const wrapper = mountPicker({ disabled: true });

        await open(wrapper);

        expect(wrapper.find('[data-panel]').exists()).toBe(false);
    });

    it('closes when clicking outside', async () => {
        const wrapper = mountPicker({ modelValue: '2026-05-15' });
        await open(wrapper);

        document.body.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
        await nextTick();

        expect(wrapper.find('[data-panel]').exists()).toBe(false);
    });

    it('renders its panel inside an enclosing <dialog> so it is not hidden behind a modal', async () => {
        const dialog = document.createElement('dialog');
        document.body.appendChild(dialog);
        const wrapper = mount(DatePicker, { props: { modelValue: '' }, attachTo: dialog, global: { plugins: [i18n] } });

        await wrapper.get('button[data-trigger]').trigger('click');
        await nextTick();

        expect(dialog.querySelector('[data-panel]')).not.toBeNull();
        wrapper.unmount();
    });

    describe('with time', () => {
        it('shows date and time in the trigger', () => {
            const text = mountPicker({ modelValue: '2026-05-15T14:30', withTime: true }).get('button[data-trigger]').text();

            expect(text).toContain('May 15, 2026');
            expect(text).toContain('2:30');
        });

        it('keeps the panel open after picking a day and preserves the time', async () => {
            const wrapper = mountPicker({ modelValue: '2026-05-15T14:30', withTime: true });
            await open(wrapper);

            await day(wrapper, '2026-05-20').trigger('click');

            expect(wrapper.emitted('update:modelValue')[0]).toEqual(['2026-05-20T14:30']);
            expect(wrapper.find('[data-panel]').exists()).toBe(true);
        });

        it('defaults a freshly picked date to midnight', async () => {
            const wrapper = mountPicker({ modelValue: '', withTime: true });
            await open(wrapper);

            await day(wrapper, '2026-05-20').trigger('click');

            expect(wrapper.emitted('update:modelValue')[0]).toEqual(['2026-05-20T00:00']);
        });

        it('updates the time from the hour and minute fields and clamps out-of-range input', async () => {
            const wrapper = mountPicker({
                modelValue: '2026-05-15T14:30',
                withTime: true,
                'onUpdate:modelValue': (value) => wrapper.setProps({ modelValue: value }),
            });
            await open(wrapper);

            await wrapper.get('input[data-hour]').setValue('9');
            expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['2026-05-15T09:30']);

            await wrapper.get('input[data-minute]').setValue('75');
            expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['2026-05-15T09:59']);

            await wrapper.get('input[data-hour]').setValue('30');
            expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['2026-05-15T23:59']);
        });

        it('closes with the Done button', async () => {
            const wrapper = mountPicker({ modelValue: '2026-05-15T14:30', withTime: true });
            await open(wrapper);

            await wrapper.get('button[data-done]').trigger('click');

            expect(wrapper.find('[data-panel]').exists()).toBe(false);
        });

        it('has no time fields for a date-only picker', async () => {
            const wrapper = mountPicker({ modelValue: '2026-05-15' });
            await open(wrapper);

            expect(wrapper.find('input[data-hour]').exists()).toBe(false);
            expect(wrapper.find('button[data-done]').exists()).toBe(false);
        });
    });
});
