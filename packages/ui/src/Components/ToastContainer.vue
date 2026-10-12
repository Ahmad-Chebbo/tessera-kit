<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useToast } from '../Composables/useToast.js';

const { t } = useI18n();
const page = usePage();
const { toasts, success, error, dismiss, pause, resume } = useToast();
const host = ref(null);

const styles = {
    success: { icon: CircleCheck, accent: 'text-status-success', border: 'border-status-success-border' },
    error: { icon: CircleAlert, accent: 'text-status-danger', border: 'border-status-danger-border' },
    warning: { icon: TriangleAlert, accent: 'text-status-warn', border: 'border-status-warn-border' },
    info: { icon: Info, accent: 'text-status-info', border: 'border-status-info-border' },
};

watch(
    () => page.flash,
    (flash) => {
        if (flash?.success) {
            success(flash.success);
        }

        if (flash?.error) {
            error(flash.error);
        }
    },
    { immediate: true },
);

async function syncTopLayer(count) {
    await nextTick();

    if (count > 0) {
        host.value?.hidePopover?.();
        host.value?.showPopover?.();
    } else {
        host.value?.hidePopover?.();
    }
}

watch(() => toasts.value.length, syncTopLayer);
onMounted(() => syncTopLayer(toasts.value.length));

const hovered = new Set();
const focused = new Set();

function enter(id) {
    hovered.add(id);
    pause(id);
}

function leave(id) {
    hovered.delete(id);

    if (!focused.has(id)) {
        resume(id);
    }
}

function focusIn(id) {
    focused.add(id);
    pause(id);
}

function focusOut(event, id) {
    if (event.currentTarget.contains(event.relatedTarget)) {
        return;
    }

    focused.delete(id);

    if (!hovered.has(id)) {
        resume(id);
    }
}
</script>

<template>
    <div
        ref="host"
        popover="manual"
        class="pointer-events-none fixed bottom-auto start-auto end-4 top-20 m-0 flex w-[22rem] max-w-[calc(100vw-2rem)] flex-col gap-2 overflow-visible border-0 bg-transparent p-0"
    >
        <TransitionGroup
            enter-active-class="transition duration-200 ease-qayema"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-qayema"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                data-toast
                :data-type="toast.type"
                :role="toast.type === 'error' ? 'alert' : 'status'"
                class="pointer-events-auto flex items-start gap-3 rounded-2xl border bg-surface p-4 shadow-pop"
                :class="styles[toast.type].border"
                @mouseenter="enter(toast.id)"
                @mouseleave="leave(toast.id)"
                @focusin="focusIn(toast.id)"
                @focusout="focusOut($event, toast.id)"
            >
                <component :is="styles[toast.type].icon" class="mt-0.5 h-5 w-5 shrink-0" :class="styles[toast.type].accent" />
                <div class="min-w-0 flex-1">
                    <p v-if="toast.title" class="text-sm font-medium text-fg">{{ toast.title }}</p>
                    <p class="text-sm" :class="toast.title ? 'mt-0.5 text-muted' : 'text-fg'">{{ toast.message }}</p>
                </div>
                <button
                    type="button"
                    data-close
                    :aria-label="t('toast.close')"
                    :title="t('toast.close')"
                    class="-me-1 -mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-control text-muted transition-colors hover:bg-hover-wash hover:text-fg"
                    @click="dismiss(toast.id)"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
