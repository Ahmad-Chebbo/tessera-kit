const DATE = /^(\d{4})-(\d{2})-(\d{2})$/;
const DATE_TIME = /^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}):(\d{2})(?::\d{2})?)?$/;

const pad = (value) => String(value).padStart(2, '0');

export function parseDate(value) {
    if (typeof value !== 'string') {
        return null;
    }

    const match = DATE.exec(value.trim());

    if (!match) {
        return null;
    }

    const [year, month, day] = match.slice(1).map(Number);
    const date = new Date(year, month - 1, day);

    return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day ? date : null;
}

export function parseDateTime(value) {
    if (typeof value !== 'string') {
        return null;
    }

    const match = DATE_TIME.exec(value.trim());

    if (!match) {
        return null;
    }

    const date = parseDate(match[1]);
    const hour = match[2] === undefined ? 0 : Number(match[2]);
    const minute = match[3] === undefined ? 0 : Number(match[3]);

    if (!date || hour > 23 || minute > 59) {
        return null;
    }

    return { date, hour, minute };
}

export function toIso(date) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function formatDate(date, locale = 'en') {
    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(date);
}

export function formatDateTime(date, hour, minute, locale = 'en') {
    const moment = new Date(date.getFullYear(), date.getMonth(), date.getDate(), hour, minute);

    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(moment);
}

export function addDays(date, days) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
}

export function addMonths(date, months) {
    const target = new Date(date.getFullYear(), date.getMonth() + months, 1);
    const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();

    return new Date(target.getFullYear(), target.getMonth(), Math.min(date.getDate(), lastDay));
}

export function buildMonthGrid(year, month, weekStart = 1) {
    const first = new Date(year, month, 1);
    const offset = (first.getDay() - weekStart + 7) % 7;

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(year, month, 1 - offset + index);

        return { date, iso: toIso(date), day: date.getDate(), inMonth: date.getMonth() === month };
    });
}

export function isDisabled(date, min, max) {
    return Boolean((min && date < min) || (max && date > max));
}

export function weekdayLabels(locale = 'en', weekStart = 1) {
    const formatter = new Intl.DateTimeFormat(locale, { weekday: 'short' });

    // 4 January 2026 is a Sunday.
    return Array.from({ length: 7 }, (_, index) => formatter.format(new Date(2026, 0, 4 + ((weekStart + index) % 7))));
}
