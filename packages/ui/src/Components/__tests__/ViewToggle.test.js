import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ViewToggle from '../ViewToggle.vue';
import { i18n } from './helpers';

describe('ViewToggle', () => {
    const mountToggle = (modelValue = 'table') =>
        mount(ViewToggle, { props: { modelValue }, global: { plugins: [i18n] } });

    it('marks the active view as pressed', () => {
        const buttons = mountToggle('grid').findAll('button');

        expect(buttons[0].attributes('aria-pressed')).toBe('false');
        expect(buttons[1].attributes('aria-pressed')).toBe('true');
    });

    it('emits the chosen view', async () => {
        const wrapper = mountToggle('table');

        await wrapper.findAll('button')[1].trigger('click');
        await wrapper.findAll('button')[0].trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([['grid'], ['table']]);
    });
});
