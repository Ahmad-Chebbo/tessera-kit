import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import PeriodPresets from '../PeriodPresets.vue';
import { i18n } from './helpers';

const presets = [
    { key: 'today', from: '2026-10-14', to: '2026-10-14' },
    { key: 'last_7_days', from: '2026-10-08', to: '2026-10-14' },
    { key: 'this_month', from: '2026-10-01', to: '2026-10-14' },
];

const mountPresets = (props = {}) => mount(PeriodPresets, { props: { presets, from: '', to: '', ...props }, global: { plugins: [i18n] } });

describe('PeriodPresets', () => {
    it('shows a tab per preset with translated labels', () => {
        const wrapper = mountPresets();

        expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['Today', 'Last 7 days', 'This month']);
    });

    it('marks the preset that matches the current range and none when it is custom', () => {
        const matching = mountPresets({ from: '2026-10-08', to: '2026-10-14' });
        expect(matching.findAll('[role="tab"]').map((tab) => tab.attributes('aria-selected'))).toEqual(['false', 'true', 'false']);

        const custom = mountPresets({ from: '2026-10-02', to: '2026-10-09' });
        expect(custom.findAll('[role="tab"]').every((tab) => tab.attributes('aria-selected') === 'false')).toBe(true);
    });

    it('emits the range of the chosen preset', async () => {
        const wrapper = mountPresets();

        await wrapper.findAll('[role="tab"]')[2].trigger('click');

        expect(wrapper.emitted('select')).toEqual([[{ from: '2026-10-01', to: '2026-10-14' }]]);
    });
});
