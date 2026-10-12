<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const scroller = ref(null);
const canScrollUp = ref(false);
const canScrollDown = ref(false);
let observer = null;

function update() {
    const element = scroller.value;

    if (!element) {
        return;
    }

    canScrollUp.value = element.scrollTop > 1;
    canScrollDown.value = element.scrollTop + element.clientHeight < element.scrollHeight - 1;
}

onMounted(() => {
    update();

    if (typeof ResizeObserver !== 'undefined' && scroller.value) {
        observer = new ResizeObserver(update);
        observer.observe(scroller.value);

        if (scroller.value.firstElementChild) {
            observer.observe(scroller.value.firstElementChild);
        }
    }
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div class="relative">
        <div ref="scroller" data-scroller class="scroll-thin h-full overflow-y-auto" @scroll.passive="update">
            <slot />
        </div>
        <div
            v-show="canScrollUp"
            data-fade-top
            class="pointer-events-none absolute inset-x-0 top-0 h-8 bg-linear-to-b from-bg to-transparent"
        ></div>
        <div
            v-show="canScrollDown"
            data-fade-bottom
            class="pointer-events-none absolute inset-x-0 bottom-0 h-8 bg-linear-to-t from-bg to-transparent"
        ></div>
    </div>
</template>
