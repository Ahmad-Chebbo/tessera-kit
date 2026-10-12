import { describe, expect, it } from 'vitest';
import { categoryLabel } from '../labels.js';

const t = (key) => ({ 'charts.other': 'أخرى', 'charts.uncategorized': 'بدون فئة' })[key] ?? key;

describe('categoryLabel', () => {
    it('translates the built-in buckets', () => {
        expect(categoryLabel({ name: 'Other', key: 'other' }, t)).toBe('أخرى');
        expect(categoryLabel({ name: 'Uncategorized', key: 'uncategorized' }, t)).toBe('بدون فئة');
    });

    it('keeps real category names, even ones called Other', () => {
        expect(categoryLabel({ name: 'Drinks', key: null }, t)).toBe('Drinks');
        expect(categoryLabel({ name: 'Other', key: null }, t)).toBe('Other');
    });
});
