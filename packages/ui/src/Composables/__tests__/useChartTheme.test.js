import { afterEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { useChartTheme } from '../useChartTheme.js';

const Probe = defineComponent({
    setup() {
        const theme = useChartTheme();

        return () => h('p', { 'data-accent': theme.value.accent });
    },
});

describe('useChartTheme', () => {
    afterEach(() => {
        document.documentElement.style.removeProperty('--gold-on');
        document.documentElement.removeAttribute('data-theme');
    });

    it('starts with the current token values', () => {
        document.documentElement.style.setProperty('--gold-on', '#F8D38D');

        const wrapper = mount(Probe);

        expect(wrapper.get('p').attributes('data-accent')).toBe('#F8D38D');
        wrapper.unmount();
    });

    it('re-reads the tokens when the light/dark theme attribute changes', async () => {
        document.documentElement.style.setProperty('--gold-on', '#F8D38D');
        const wrapper = mount(Probe);

        document.documentElement.style.setProperty('--gold-on', '#C39B50');
        document.documentElement.setAttribute('data-theme', 'dark');
        await new Promise((resolve) => setTimeout(resolve, 0));
        await nextTick();

        expect(wrapper.get('p').attributes('data-accent')).toBe('#C39B50');
        wrapper.unmount();
    });

    it('stops observing once the component is unmounted', async () => {
        const wrapper = mount(Probe);
        wrapper.unmount();

        document.documentElement.setAttribute('data-theme', 'dark');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(() => document.documentElement.setAttribute('data-theme', 'light')).not.toThrow();
    });
});
