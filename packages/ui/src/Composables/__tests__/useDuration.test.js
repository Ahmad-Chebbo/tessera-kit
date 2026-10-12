import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { defineComponent, h } from 'vue';
import { useDuration } from '../useDuration';

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en: { common: { minutesShort: '{n} min' } } } });

function run(minutes) {
    let result;
    const Probe = defineComponent({
        setup() {
            result = useDuration().formatMinutes(minutes);

            return () => h('div');
        },
    });
    mount(Probe, { global: { plugins: [i18n] } });

    return result;
}

describe('useDuration', () => {
    it('formats minutes only below an hour', () => {
        expect(run(45)).toBe('45 min');
    });

    it('formats whole hours', () => {
        expect(run(480)).toBe('8h');
    });

    it('formats hours and minutes', () => {
        expect(run(450)).toBe('7h 30m');
    });

    it('treats bad input as zero', () => {
        expect(run(null)).toBe('0 min');
        expect(run(-5)).toBe('0 min');
    });
});
