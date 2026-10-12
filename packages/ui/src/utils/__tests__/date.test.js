import { describe, expect, it } from 'vitest';
import {
    addDays,
    addMonths,
    buildMonthGrid,
    formatDate,
    formatDateTime,
    isDisabled,
    parseDate,
    parseDateTime,
    toIso,
    weekdayLabels,
} from '../date.js';

describe('parseDate', () => {
    it('parses a valid ISO date into a local date', () => {
        const date = parseDate('2026-05-15');

        expect([date.getFullYear(), date.getMonth(), date.getDate()]).toEqual([2026, 4, 15]);
    });

    it.each(['', null, undefined, 'nope', '2026-02-30', '2026-13-01', '2026-5-1', 'tomorrow'])('rejects %j', (value) => {
        expect(parseDate(value)).toBeNull();
    });
});

describe('parseDateTime', () => {
    it('reads date and time', () => {
        const { date, hour, minute } = parseDateTime('2026-05-15T14:30');

        expect(toIso(date)).toBe('2026-05-15');
        expect([hour, minute]).toEqual([14, 30]);
    });

    it('also accepts a space separator and seconds', () => {
        expect(parseDateTime('2026-05-15 09:05:00')).toMatchObject({ hour: 9, minute: 5 });
    });

    it('treats a bare date as midnight and rejects bad times', () => {
        expect(parseDateTime('2026-05-15')).toMatchObject({ hour: 0, minute: 0 });
        expect(parseDateTime('2026-05-15T25:00')).toBeNull();
        expect(parseDateTime('garbage')).toBeNull();
    });
});

describe('toIso and formatting', () => {
    it('zero-pads month and day', () => {
        expect(toIso(new Date(2026, 0, 5))).toBe('2026-01-05');
    });

    it('formats for display in a locale', () => {
        expect(formatDate(new Date(2026, 4, 15), 'en')).toBe('May 15, 2026');
        expect(formatDateTime(new Date(2026, 4, 15), 14, 30, 'en')).toContain('2:30');
    });
});

describe('addDays and addMonths', () => {
    it('moves across month boundaries', () => {
        expect(toIso(addDays(new Date(2026, 0, 31), 1))).toBe('2026-02-01');
        expect(toIso(addDays(new Date(2026, 2, 1), -1))).toBe('2026-02-28');
    });

    it('clamps the day when the target month is shorter', () => {
        expect(toIso(addMonths(new Date(2026, 0, 31), 1))).toBe('2026-02-28');
        expect(toIso(addMonths(new Date(2026, 4, 15), -5))).toBe('2025-12-15');
    });
});

describe('buildMonthGrid', () => {
    it('always returns six full weeks starting on the requested weekday', () => {
        const monday = buildMonthGrid(2026, 4, 1);

        expect(monday).toHaveLength(42);
        expect(monday[0].iso).toBe('2026-04-27');
        expect(monday[41].iso).toBe('2026-06-07');

        const sunday = buildMonthGrid(2026, 4, 0);
        expect(sunday[0].iso).toBe('2026-04-26');
    });

    it('marks days that belong to the shown month', () => {
        const grid = buildMonthGrid(2026, 4, 1);

        expect(grid.filter((cell) => cell.inMonth)).toHaveLength(31);
        expect(grid[0].inMonth).toBe(false);
        expect(grid[4]).toMatchObject({ iso: '2026-05-01', inMonth: true });
    });
});

describe('isDisabled', () => {
    const min = parseDate('2026-05-10');
    const max = parseDate('2026-05-20');

    it('disables days outside the range, inclusive of the bounds', () => {
        expect(isDisabled(parseDate('2026-05-09'), min, max)).toBe(true);
        expect(isDisabled(parseDate('2026-05-10'), min, max)).toBe(false);
        expect(isDisabled(parseDate('2026-05-20'), min, max)).toBe(false);
        expect(isDisabled(parseDate('2026-05-21'), min, max)).toBe(true);
    });

    it('allows everything without bounds', () => {
        expect(isDisabled(parseDate('1999-01-01'), null, null)).toBe(false);
    });
});

describe('weekdayLabels', () => {
    it('starts on the requested weekday', () => {
        expect(weekdayLabels('en', 1)[0]).toBe('Mon');
        expect(weekdayLabels('en', 0)[0]).toBe('Sun');
        expect(weekdayLabels('en', 1)).toHaveLength(7);
    });
});
