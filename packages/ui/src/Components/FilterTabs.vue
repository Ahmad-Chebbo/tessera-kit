<script setup>
import { nextTick, ref } from 'vue';

const props = defineProps({
    modelValue: { type: null, default: '' },
    tabs: { type: Array, required: true },
});

const emit = defineEmits(['update:modelValue']);

const same = (a, b) => String(a ?? '') === String(b ?? '');
const buttons = ref([]);

function choose(index) {
    const target = props.tabs[index];

    if (!target) {
        return;
    }

    emit('update:modelValue', target.value);
    nextTick(() => buttons.value[index]?.focus());
}

function onKeydown(event, index) {
    const rtl = typeof document !== 'undefined' && document.documentElement.dir === 'rtl';
    const forward = rtl ? 'ArrowLeft' : 'ArrowRight';
    const backward = rtl ? 'ArrowRight' : 'ArrowLeft';
    let targetIndex = null;

    if (event.key === forward) {
        targetIndex = index + 1;
    } else if (event.key === backward) {
        targetIndex = index - 1;
    } else if (event.key === 'Home') {
        targetIndex = 0;
    } else if (event.key === 'End') {
        targetIndex = props.tabs.length - 1;
    }

    if (targetIndex !== null && props.tabs[targetIndex]) {
        event.preventDefault();
        choose(targetIndex);
    }
}
</script>

<template>
    <div role="tablist" class="scroll-thin flex gap-2 overflow-x-auto pb-1">
        <button
            v-for="(tab, index) in tabs"
            :key="String(tab.value)"
            type="button"
            :ref="(element) => (buttons[index] = element)"
            role="tab"
            :aria-selected="same(modelValue, tab.value)"
            :tabindex="same(modelValue, tab.value) ? 0 : -1"
            class="inline-flex shrink-0 items-center gap-1.5 rounded-control border px-3.5 py-1.5 text-sm font-medium transition-colors duration-150 ease-qayema focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-gold-on"
            :class="
                same(modelValue, tab.value)
                    ? 'border-accent-border bg-accent-wash text-accent'
                    : 'border-line bg-surface text-muted hover:bg-hover-wash hover:text-fg'
            "
            @click="emit('update:modelValue', tab.value)"
            @keydown="onKeydown($event, index)"
        >
            {{ tab.label }} <span v-if="tab.count !== undefined && tab.count !== null" class="text-xs font-normal text-faint">{{ tab.count }}</span>
        </button>
    </div>
</template>
