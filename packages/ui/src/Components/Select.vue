<script setup>
import { Comment, Fragment, Text, computed, nextTick, onBeforeUnmount, ref, useId, useSlots, watch } from 'vue';
import { Check, ChevronDown } from 'lucide-vue-next';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: null, default: '' },
    options: { type: Array, default: null },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    size: { type: String, default: 'md', validator: (value) => ['md', 'sm'].includes(value) },
    id: { type: String, default: undefined },
});

const emit = defineEmits(['update:modelValue', 'change']);
const slots = useSlots();

const root = ref(null);
const trigger = ref(null);
const panel = ref(null);
const open = ref(false);
const highlighted = ref(-1);
const panelStyle = ref({});
const teleportTarget = ref('body');
const uid = useId();
const listId = `select-list-${uid}`;
const optionId = (index) => `select-option-${uid}-${index}`;

function textOf(children) {
    if (typeof children === 'string') {
        return children;
    }

    if (Array.isArray(children)) {
        return children
            .map((child) => {
                if (typeof child === 'string') {
                    return child;
                }

                return child?.type === Text ? child.children : textOf(child?.children);
            })
            .join('');
    }

    return '';
}

function collect(nodes, out) {
    for (const node of nodes) {
        if (Array.isArray(node)) {
            collect(node, out);
        } else if (node && node.type === Fragment) {
            collect(node.children ?? [], out);
        } else if (node && node.type !== Comment && node.type === 'option') {
            const label = textOf(node.children).trim();
            const nodeProps = node.props ?? {};

            out.push({
                value: 'value' in nodeProps ? nodeProps.value : label,
                label,
                disabled: nodeProps.disabled !== undefined && nodeProps.disabled !== false,
            });
        }
    }
}

const items = computed(() => {
    if (props.options) {
        return props.options.map((option) => ({ disabled: false, ...option }));
    }

    const out = [];
    collect(slots.default?.() ?? [], out);

    return out;
});

const sameValue = (a, b) => String(a ?? '') === String(b ?? '');

const selectedIndex = computed(() => items.value.findIndex((item) => sameValue(item.value, props.modelValue)));
const selectedItem = computed(() => (selectedIndex.value >= 0 ? items.value[selectedIndex.value] : null));
const hasValue = computed(() => selectedItem.value !== null && selectedItem.value.label !== '');

function nextEnabled(from, step) {
    let index = from + step;

    while (index >= 0 && index < items.value.length) {
        if (!items.value[index].disabled) {
            return index;
        }
        index += step;
    }

    return from;
}

function reposition() {
    if (!trigger.value) {
        return;
    }

    const rect = trigger.value.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const flip = spaceBelow < 260 && rect.top > spaceBelow;

    const width = Math.max(rect.width, 160);
    const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));

    panelStyle.value = {
        position: 'fixed',
        left: `${left}px`,
        width: `${width}px`,
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
    if (panel.value?.contains(event?.target)) {
        return;
    }

    reposition();
}

function openPanel() {
    if (props.disabled || open.value) {
        return;
    }

    teleportTarget.value = root.value?.closest('dialog') ?? 'body';
    open.value = true;
    highlighted.value = selectedIndex.value >= 0 && !items.value[selectedIndex.value].disabled
        ? selectedIndex.value
        : nextEnabled(-1, 1);
    reposition();
}

function closePanel() {
    open.value = false;
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('mousedown', onPointerDown);
        window.addEventListener('scroll', onViewportChange, true);
        window.addEventListener('resize', onViewportChange);
        nextTick(scrollHighlightedIntoView);
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

function scrollHighlightedIntoView() {
    panel.value?.querySelector('[data-highlighted="true"]')?.scrollIntoView?.({ block: 'nearest' });
}

function select(index) {
    const item = items.value[index];

    if (!item || item.disabled) {
        return;
    }

    if (sameValue(item.value, props.modelValue)) {
        closePanel();
        return;
    }

    emit('update:modelValue', item.value);
    emit('change', item.value);
    closePanel();
}

function toggle() {
    if (open.value) {
        closePanel();
    } else {
        openPanel();
    }
}

function onKeydown(event) {
    if (props.disabled) {
        return;
    }

    switch (event.key) {
        case 'ArrowDown':
        case 'ArrowUp': {
            event.preventDefault();

            if (!open.value) {
                openPanel();
                break;
            }

            highlighted.value = nextEnabled(highlighted.value, event.key === 'ArrowDown' ? 1 : -1);
            nextTick(scrollHighlightedIntoView);
            break;
        }
        case 'Home':
        case 'End': {
            if (open.value) {
                event.preventDefault();
                highlighted.value =
                    event.key === 'Home' ? nextEnabled(-1, 1) : nextEnabled(items.value.length, -1);
                nextTick(scrollHighlightedIntoView);
            }
            break;
        }
        case 'Enter':
        case ' ': {
            event.preventDefault();

            if (open.value) {
                select(highlighted.value);
            } else {
                openPanel();
            }
            break;
        }
        case 'Escape': {
            if (open.value) {
                event.preventDefault();
                closePanel();
            }
            break;
        }
        case 'Tab': {
            closePanel();
            break;
        }
        default: {
            if (event.key.length === 1 && !event.ctrlKey && !event.metaKey) {
                const needle = event.key.toLowerCase();
                const match = items.value.findIndex(
                    (item) => !item.disabled && item.label.toLowerCase().startsWith(needle),
                );

                if (match >= 0) {
                    if (!open.value) {
                        openPanel();
                    }
                    highlighted.value = match;
                    nextTick(scrollHighlightedIntoView);
                }
            }
        }
    }
}
</script>

<template>
    <div ref="root" class="relative" :class="$attrs.class" :style="$attrs.style">
        <button
            :id="id"
            ref="trigger"
            type="button"
            role="combobox"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :aria-controls="open ? listId : undefined"
            :aria-activedescendant="open && highlighted >= 0 ? optionId(highlighted) : undefined"
            :disabled="disabled"
            class="flex w-full items-center justify-between gap-2 border bg-field text-start transition duration-150 ease-qayema focus:outline-hidden disabled:cursor-not-allowed disabled:bg-disabled-wash disabled:opacity-60"
            :class="[
                size === 'sm' ? 'rounded-control p-2 text-xs' : 'rounded-field p-3 text-sm',
                open
                    ? 'border-gold-on ring-2 ring-ring-accent'
                    : invalid
                      ? 'border-status-danger-border'
                      : 'border-line hover:border-line-strong focus:border-gold-on focus:ring-2 focus:ring-ring-accent',
            ]"
            @click="toggle"
            @keydown="onKeydown"
        >
            <span class="truncate" :class="hasValue ? 'text-fg' : 'text-placeholder'">
                {{ hasValue ? selectedItem.label : placeholder || selectedItem?.label || '' }}
            </span>
            <ChevronDown
                class="h-4 w-4 shrink-0 text-muted transition-transform duration-150"
                :class="open ? 'rotate-180' : ''"
            />
        </button>

        <input
            v-if="required"
            class="pointer-events-none absolute inset-0 h-full w-full opacity-0"
            tabindex="-1"
            aria-hidden="true"
            required
            :value="String(modelValue ?? '')"
            @focus="trigger?.focus()"
            @input.prevent
        />

        <Teleport :to="teleportTarget">
            <Transition
                enter-active-class="transition duration-100 ease-qayema"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-75 ease-qayema"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <ul
                    v-if="open"
                    ref="panel"
                    :id="listId"
                    role="listbox"
                    :style="panelStyle"
                    class="scroll-thin z-[70] max-h-64 overflow-auto rounded-2xl border border-line bg-surface p-1.5 shadow-pop"
                >
                    <li
                        v-for="(item, index) in items"
                        :key="index"
                        :id="optionId(index)"
                        role="option"
                        :aria-selected="index === selectedIndex"
                        :aria-disabled="item.disabled"
                        :data-highlighted="index === highlighted"
                        class="flex cursor-pointer items-center justify-between gap-3 rounded-control px-3 py-2.5 text-sm transition-colors duration-100"
                        :class="[
                            item.disabled
                                ? 'cursor-not-allowed text-faint opacity-60'
                                : index === selectedIndex
                                  ? 'bg-accent-wash text-accent'
                                  : index === highlighted
                                    ? 'bg-hover-wash text-fg'
                                    : 'text-fg',
                        ]"
                        @mouseenter="!item.disabled && (highlighted = index)"
                        @click="select(index)"
                    >
                        <span class="truncate">{{ item.label }}</span>
                        <Check v-if="index === selectedIndex" class="h-4 w-4 shrink-0" />
                    </li>
                </ul>
            </Transition>
        </Teleport>
    </div>
</template>
