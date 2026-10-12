<script setup>
import { computed, useId } from 'vue';
import { CircleHelp, TriangleAlert } from 'lucide-vue-next';
import Modal from './Modal.vue';
import PrimaryButton from './PrimaryButton.vue';
import DangerButton from './DangerButton.vue';
import SecondaryButton from './SecondaryButton.vue';
import { confirmState, resolveConfirm } from '../Composables/useConfirm.js';

const danger = computed(() => confirmState.variant === 'danger');
const uid = useId();
const titleId = `confirm-title-${uid}`;
const messageId = `confirm-message-${uid}`;
</script>

<template>
    <Modal :show="confirmState.open" max-width="md" @close="resolveConfirm(false)">
        <div
            class="p-6"
            :role="danger ? 'alertdialog' : 'dialog'"
            :aria-labelledby="titleId"
            :aria-describedby="confirmState.message ? messageId : undefined"
        >
            <div class="flex items-start gap-4">
                <span
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                    :class="danger ? 'bg-status-danger-wash text-status-danger' : 'bg-accent-wash text-accent'"
                >
                    <component :is="danger ? TriangleAlert : CircleHelp" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h2 :id="titleId" class="font-display text-xl font-normal text-fg">{{ confirmState.title }}</h2>
                    <p v-if="confirmState.message" :id="messageId" class="mt-1 text-sm text-muted">{{ confirmState.message }}</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="resolveConfirm(false)">{{ confirmState.cancelText }}</SecondaryButton>
                <DangerButton v-if="danger" type="button" @click="resolveConfirm(true)">
                    {{ confirmState.confirmText }}
                </DangerButton>
                <PrimaryButton v-else type="button" @click="resolveConfirm(true)">
                    {{ confirmState.confirmText }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
