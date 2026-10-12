<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { CalendarDays, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import {
    addDays,
    addMonths,
    buildMonthGrid,
    formatDate,
    formatDateTime,
    isDisabled,
    parseDateTime,
    toIso,
    weekdayLabels,
} from '../utils/date.js';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: String, default: '' },
    withTime: { type: Boolean, default: false },
    min: { type: String, default: null },
    max: { type: String, default: null },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    invalid: { type: Boolean, default: false },
    id: { type: String, default: undefined },
    weekStart: { type: Number, default: 1 },
});

const emit = defineEmits(['update:modelValue', 'change']);
const { t } = useI18n();

const root = ref(null);
const trigger = ref(null);
const panel = ref(null);
const open = ref(false);
const panelStyle = ref({});
const teleportTarget = ref('body');
const locale = ref('en');
const viewYear = ref(new Date().getFullYear());
const viewMonth = ref(new Date().getMonth());
const focusedIso = ref('');

const PANEL_WIDTH = 288;

const parsed = computed(() => parseDateTime(props.modelValue));
const selectedIso = computed(() => (parsed.value ? toIso(parsed.value.date) : ''));
const minDate = computed(() => parseDateTime(props.min)?.date ?? null);
const maxDate = computed(() => parseDateTime(props.max)?.date ?? null);

const today = () => new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate());
const todayIso = computed(() => (open.value ? toIso(today()) : ''));

const displayText = computed(() => {
    if (!parsed.value) {
        return '';
    }

    return props.withTime
        ? formatDateTime(parsed.value.date, parsed.value.hour, parsed.value.minute, locale.value)
        : formatDate(parsed.value.date, locale.value);
});

const grid = computed(() => buildMonthGrid(viewYear.value, viewMonth.value, props.weekStart));
const weekdays = computed(() => weekdayLabels(locale.value, props.weekStart));
const title = computed(() =>
    new Date(viewYear.value, viewMonth.value, 1).toLocaleDateString(locale.value, { month: 'long', year: 'numeric' }),
);
const todayDisabled = computed(() => isDisabled(today(), minDate.value, maxDate.value));

const time = computed(() => ({ hour: parsed.value?.hour ?? 0, minute: parsed.value?.minute ?? 0 }));

const pad = (value) => String(value).padStart(2, '0');

function emitValue(date, hour = time.value.hour, minute = time.value.minute) {
    const value = props.withTime ? `${toIso(date)}T${pad(hour)}:${pad(minute)}` : toIso(date);

    emit('update:modelValue', value);
    emit('change', value);
}

function showMonthOf(date) {
    viewYear.value = date.getFullYear();
    viewMonth.value = date.getMonth();
}

function reposition() {
    if (!trigger.value) {
        return;
    }

    const rect = trigger.value.getBoundingClientRect();
    const flip = window.innerHeight - rect.bottom < 380 && rect.top > window.innerHeight - rect.bottom;
    const left = Math.max(8, Math.min(rect.left, window.innerWidth - PANEL_WIDTH - 8));

    panelStyle.value = {
        position: 'fixed',
        left: `${left}px`,
        width: `${PANEL_WIDTH}px`,
        ...(flip ? { bottom: `${window.innerHeight - rect.top + 8}px` } : { top: `${rect.bottom + 8}px` }),
    };
}

function onPointerDown(event) {
    if (root.value?.contains(event.target) || panel.value?.contains(event.target)) {
        return;
    }

    closePanel();
}

function onViewportChange(event) {
    if (!panel.value?.contains(event?.target)) {
        reposition();
    }
}

function focusDay() {
    nextTick(() => panel.value?.querySelector(`[data-day="${focusedIso.value}"]`)?.focus?.());
}

function openPanel(focus = false) {
    if (props.disabled || open.value) {
        return;
    }

    locale.value = document.documentElement.lang || 'en';
    teleportTarget.value = root.value?.closest('dialog') ?? 'body';

    const anchor = parsed.value?.date ?? today();
    showMonthOf(anchor);
    focusedIso.value = toIso(anchor);
    open.value = true;
    reposition();

    if (focus) {
        focusDay();
    }
}

function closePanel() {
    open.value = false;
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('mousedown', onPointerDown);
        window.addEventListener('scroll', onViewportChange, true);
        window.addEventListener('resize', onViewportChange);
    } else {
        document.removeEventListener('mousedown', onPointerDown);
        window.removeEventListener('scroll', onViewportChange, true);
        window.removeEventListener('resize', onViewportChange);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onPointerDown);
    window.removeEventListener('scroll', onViewportChange, true);
    window.removeEventListener('resize', onViewportChange);
});

function pickDay(cell) {
    if (isDisabled(cell.date, minDate.value, maxDate.value)) {
        return;
    }

    focusedIso.value = cell.iso;
    emitValue(cell.date);

    if (!props.withTime) {
        closePanel();
        trigger.value?.focus();
    }
}

function pickToday() {
    if (todayDisabled.value) {
        return;
    }

    showMonthOf(today());
    pickDay({ date: today(), iso: toIso(today()) });
}

function clear() {
    emit('update:modelValue', '');
    emit('change', '');
    closePanel();
}

function navigate(months) {
    showMonthOf(addMonths(new Date(viewYear.value, viewMonth.value, 1), months));
}

function setTime(field, raw) {
    const value = parseInt(raw, 10);

    if (Number.isNaN(value)) {
        return;
    }

    const limit = field === 'hour' ? 23 : 59;
    const next = { ...time.value, [field]: Math.min(Math.max(value, 0), limit) };

    emitValue(parsed.value?.date ?? today(), next.hour, next.minute);
}

function moveFocus(date) {
    focusedIso.value = toIso(date);
    showMonthOf(date);
    focusDay();
}

function onGridKeydown(event) {
    const current = parseDateTime(focusedIso.value)?.date ?? today();
    const rtl = document.documentElement.dir === 'rtl';
    const step = { ArrowLeft: rtl ? 1 : -1, ArrowRight: rtl ? -1 : 1, ArrowUp: -7, ArrowDown: 7 }[event.key];

    if (step !== undefined) {
        event.preventDefault();
        moveFocus(addDays(current, step));

        return;
    }

    switch (event.key) {
        case 'PageUp':
        case 'PageDown': {
            event.preventDefault();
            const direction = event.key === 'PageUp' ? -1 : 1;
            moveFocus(addMonths(current, event.shiftKey ? direction * 12 : direction));
            break;
        }
        case 'Home':
        case 'End': {
            event.preventDefault();
            const offset = (current.getDay() - props.weekStart + 7) % 7;
            moveFocus(addDays(current, event.key === 'Home' ? -offset : 6 - offset));
            break;
        }
        case 'Enter':
        case ' ': {
            event.preventDefault();
            pickDay({ date: current, iso: toIso(current) });
            break;
        }
        case 'Escape': {
            event.preventDefault();
            closePanel();
            trigger.value?.focus();
            break;
        }
    }
}

function onTriggerKeydown(event) {
    if (props.disabled) {
        return;
    }

    if (['ArrowDown', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        open.value ? closePanel() : openPanel(true);
    } else if (event.key === 'Escape' && open.value) {
        event.preventDefault();
        closePanel();
    }
}

function dayClasses(cell) {
    const disabled = isDisabled(cell.date, minDate.value, maxDate.value);

    return [
        cell.iso === selectedIso.value
            ? 'bg-accent-fill font-semibold text-ink'
            : cell.inMonth
              ? 'text-fg hover:bg-hover-wash'
              : 'text-faint hover:bg-hover-wash',
        cell.iso === todayIso.value && cell.iso !== selectedIso.value ? 'ring-1 ring-gold-on' : '',
        disabled ? 'cursor-not-allowed opacity-40 hover:bg-transparent' : '',
    ];
}
</script>

<template>
    <div ref="root" class="relative" :class="$attrs.class" :style="$attrs.style">
        <button
            :id="id"
            ref="trigger"
            type="button"
            data-trigger
            aria-haspopup="dialog"
            :aria-expanded="open"
            :disabled="disabled"
            class="flex w-full items-center gap-2 border bg-field px-3 py-2 text-start text-sm transition duration-150 ease-qayema focus:outline-hidden disabled:cursor-not-allowed disabled:bg-disabled-wash disabled:opacity-60 rounded-field"
            :class="[
                clearable && modelValue ? 'pe-9' : '',
                open
                    ? 'border-gold-on ring-2 ring-ring-accent'
                    : invalid
                      ? 'border-status-danger-border'
                      : 'border-line hover:border-line-strong focus:border-gold-on focus:ring-2 focus:ring-ring-accent',
            ]"
            @click="open ? closePanel() : openPanel()"
            @keydown="onTriggerKeydown"
        >
            <CalendarDays class="h-4 w-4 shrink-0 text-muted" />
            <span class="truncate" :class="displayText ? 'text-fg' : 'text-placeholder'">
                {{ displayText || placeholder || (withTime ? t('datepicker.placeholderTime') : t('datepicker.placeholder')) }}
            </span>
        </button>

        <button
            v-if="clearable && modelValue && !disabled"
            type="button"
            data-clear-trigger
            :aria-label="t('datepicker.clear')"
            :title="t('datepicker.clear')"
            class="absolute end-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-muted transition-colors hover:bg-hover-wash hover:text-fg"
            @click="clear"
        >
            <X class="h-3.5 w-3.5" />
        </button>

        <Teleport :to="teleportTarget">
            <Transition
                enter-active-class="transition duration-100 ease-qayema"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-75 ease-qayema"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="open"
                    ref="panel"
                    data-panel
                    role="dialog"
                    :aria-label="t('datepicker.chooseDate')"
                    :style="panelStyle"
                    class="z-[70] rounded-2xl border border-line bg-surface p-3 shadow-pop"
                >
                    <div class="mb-2 flex items-center justify-between gap-1">
                        <div class="flex items-center">
                            <button
                                type="button"
                                data-nav="prev-year"
                                :aria-label="t('datepicker.previousYear')"
                                class="flex h-8 w-8 items-center justify-center rounded-control text-muted transition-colors hover:bg-hover-wash hover:text-fg"
                                @click="navigate(-12)"
                            >
                                <ChevronsLeft class="h-4 w-4 rtl:rotate-180" />
                            </button>
                            <button
                                type="button"
                                data-nav="prev-month"
                                :aria-label="t('datepicker.previousMonth')"
                                class="flex h-8 w-8 items-center justify-center rounded-control text-muted transition-colors hover:bg-hover-wash hover:text-fg"
                                @click="navigate(-1)"
                            >
                                <ChevronLeft class="h-4 w-4 rtl:rotate-180" />
                            </button>
                        </div>

                        <p data-title class="font-display text-lg font-normal text-fg" aria-live="polite">{{ title }}</p>

                        <div class="flex items-center">
                            <button
                                type="button"
                                data-nav="next-month"
                                :aria-label="t('datepicker.nextMonth')"
                                class="flex h-8 w-8 items-center justify-center rounded-control text-muted transition-colors hover:bg-hover-wash hover:text-fg"
                                @click="navigate(1)"
                            >
                                <ChevronRight class="h-4 w-4 rtl:rotate-180" />
                            </button>
                            <button
                                type="button"
                                data-nav="next-year"
                                :aria-label="t('datepicker.nextYear')"
                                class="flex h-8 w-8 items-center justify-center rounded-control text-muted transition-colors hover:bg-hover-wash hover:text-fg"
                                @click="navigate(12)"
                            >
                                <ChevronsRight class="h-4 w-4 rtl:rotate-180" />
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-7 text-center">
                        <span
                            v-for="label in weekdays"
                            :key="label"
                            data-weekday
                            class="py-1 text-xs font-medium text-faint"
                        >
                            {{ label }}
                        </span>
                    </div>

                    <div data-grid class="grid grid-cols-7 gap-y-0.5 text-center" @keydown="onGridKeydown">
                        <button
                            v-for="cell in grid"
                            :key="cell.iso"
                            type="button"
                            :data-day="cell.iso"
                            :data-today="cell.iso === todayIso ? '' : undefined"
                            :aria-pressed="cell.iso === selectedIso"
                            :aria-label="formatDate(cell.date, locale)"
                            :disabled="isDisabled(cell.date, minDate, maxDate)"
                            :tabindex="cell.iso === focusedIso ? 0 : -1"
                            class="mx-auto flex h-9 w-9 items-center justify-center rounded-control text-sm transition-colors duration-100 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring-accent"
                            :class="dayClasses(cell)"
                            @click="pickDay(cell)"
                        >
                            {{ cell.day }}
                        </button>
                    </div>

                    <div v-if="withTime" class="mt-3 flex items-center justify-center gap-2 border-t border-line-2 pt-3">
                        <label class="text-xs text-muted" for="datepicker-hour">{{ t('datepicker.hour') }}</label>
                        <input
                            id="datepicker-hour"
                            data-hour
                            type="number"
                            min="0"
                            max="23"
                            inputmode="numeric"
                            class="w-14 rounded-control border border-line bg-field px-2 py-1 text-center text-sm text-fg focus:border-gold-on focus:outline-hidden focus:ring-2 focus:ring-ring-accent"
                            :value="time.hour"
                            @input="setTime('hour', $event.target.value)"
                        />
                        <span class="text-muted">:</span>
                        <input
                            id="datepicker-minute"
                            data-minute
                            type="number"
                            min="0"
                            max="59"
                            inputmode="numeric"
                            :aria-label="t('datepicker.minute')"
                            class="w-14 rounded-control border border-line bg-field px-2 py-1 text-center text-sm text-fg focus:border-gold-on focus:outline-hidden focus:ring-2 focus:ring-ring-accent"
                            :value="pad(time.minute)"
                            @input="setTime('minute', $event.target.value)"
                        />
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-line-2 pt-3">
                        <button
                            type="button"
                            data-today-btn
                            :disabled="todayDisabled"
                            class="rounded-control px-2 py-1 text-sm font-medium text-accent transition-colors hover:bg-accent-wash disabled:cursor-not-allowed disabled:opacity-40"
                            @click="pickToday"
                        >
                            {{ t('datepicker.today') }}
                        </button>
                        <div class="flex items-center gap-1">
                            <button
                                v-if="clearable && modelValue"
                                type="button"
                                data-clear
                                class="rounded-control px-2 py-1 text-sm text-muted transition-colors hover:bg-hover-wash hover:text-fg"
                                @click="clear"
                            >
                                {{ t('datepicker.clear') }}
                            </button>
                            <button
                                v-if="withTime"
                                type="button"
                                data-done
                                class="rounded-control bg-gold px-3 py-1 text-sm font-semibold text-ink transition-colors hover:bg-gold-deep"
                                @click="closePanel"
                            >
                                {{ t('datepicker.done') }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
