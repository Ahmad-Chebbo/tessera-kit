import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import ScrollArea from '../ScrollArea.vue';

const setMetrics = (element, { scrollHeight, clientHeight, scrollTop }) => {
    Object.defineProperty(element, 'scrollHeight', { value: scrollHeight, configurable: true });
    Object.defineProperty(element, 'clientHeight', { value: clientHeight, configurable: true });
    Object.defineProperty(element, 'scrollTop', { value: scrollTop, configurable: true, writable: true });
};

const visible = (wrapper) => ({
    top: wrapper.get('[data-fade-top]').element.style.display !== 'none',
    bottom: wrapper.get('[data-fade-bottom]').element.style.display !== 'none',
});

describe('ScrollArea', () => {
    it('shows no fades when the content fits', async () => {
        const wrapper = mount(ScrollArea, { slots: { default: '<p>short</p>' } });
        const scroller = wrapper.get('[data-scroller]');
        setMetrics(scroller.element, { scrollHeight: 100, clientHeight: 100, scrollTop: 0 });

        await scroller.trigger('scroll');
        await nextTick();

        expect(visible(wrapper)).toEqual({ top: false, bottom: false });
    });

    it('shows only the bottom fade at the top of long content', async () => {
        const wrapper = mount(ScrollArea, { slots: { default: '<p>long</p>' } });
        const scroller = wrapper.get('[data-scroller]');
        setMetrics(scroller.element, { scrollHeight: 500, clientHeight: 100, scrollTop: 0 });

        await scroller.trigger('scroll');
        await nextTick();

        expect(visible(wrapper)).toEqual({ top: false, bottom: true });
    });

    it('shows both fades in the middle and only the top fade at the end', async () => {
        const wrapper = mount(ScrollArea, { slots: { default: '<p>long</p>' } });
        const scroller = wrapper.get('[data-scroller]');

        setMetrics(scroller.element, { scrollHeight: 500, clientHeight: 100, scrollTop: 150 });
        await scroller.trigger('scroll');
        await nextTick();
        expect(visible(wrapper)).toEqual({ top: true, bottom: true });

        setMetrics(scroller.element, { scrollHeight: 500, clientHeight: 100, scrollTop: 400 });
        await scroller.trigger('scroll');
        await nextTick();
        expect(visible(wrapper)).toEqual({ top: true, bottom: false });
    });
});
