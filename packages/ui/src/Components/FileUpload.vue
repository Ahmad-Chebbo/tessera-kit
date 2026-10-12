<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { CloudUpload, FileText, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    modelValue: { type: [File, null], default: null },
    accept: { type: String, default: 'image/*' },
    previewUrl: { type: String, default: null },
    error: { type: String, default: null },
    maxSizeMb: { type: Number, default: null },
    id: { type: String, default: undefined },
    hint: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue', 'change', 'remove']);
const { t } = useI18n();

const input = ref(null);
const dragging = ref(false);
const localError = ref(null);
const objectUrl = ref(null);

const isImage = computed(() => props.modelValue?.type?.startsWith('image/') ?? false);
const shownImage = computed(() => (isImage.value ? objectUrl.value : !props.modelValue ? props.previewUrl : null));
const shownError = computed(() => localError.value || props.error);

watch(
    () => props.modelValue,
    (file) => {
        if (objectUrl.value) {
            URL.revokeObjectURL(objectUrl.value);
            objectUrl.value = null;
        }

        if (file?.type?.startsWith('image/')) {
            objectUrl.value = URL.createObjectURL(file);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (objectUrl.value) {
        URL.revokeObjectURL(objectUrl.value);
    }
});

function matchesAccept(file) {
    const rules = props.accept
        .split(',')
        .map((rule) => rule.trim().toLowerCase())
        .filter(Boolean);

    if (rules.length === 0) {
        return true;
    }

    return rules.some((rule) => {
        if (rule === '*/*') {
            return true;
        }

        if (rule.startsWith('.')) {
            return file.name.toLowerCase().endsWith(rule);
        }

        if (rule.endsWith('/*')) {
            return file.type.toLowerCase().startsWith(rule.slice(0, -1));
        }

        return file.type.toLowerCase() === rule;
    });
}

function formatSize(bytes) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    return bytes < 1024 * 1024 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function acceptFile(file) {
    if (!file) {
        return;
    }

    if (!matchesAccept(file)) {
        localError.value = t('upload.notAccepted', { name: file.name });
        return;
    }

    if (props.maxSizeMb && file.size > props.maxSizeMb * 1024 * 1024) {
        localError.value = t('upload.tooLarge', { name: file.name, size: props.maxSizeMb });
        return;
    }

    localError.value = null;
    emit('update:modelValue', file);
    emit('change', file);
}

function onInputChange(event) {
    acceptFile(event.target.files?.[0] ?? null);
    event.target.value = '';
}

function onDrop(event) {
    dragging.value = false;
    acceptFile(event.dataTransfer?.files?.[0] ?? null);
}

function remove() {
    localError.value = null;
    emit('update:modelValue', null);
    emit('remove');
}

function browse() {
    input.value?.click();
}
</script>

<template>
    <div>
        <div
            data-dropzone
            role="button"
            tabindex="0"
            class="group relative flex cursor-pointer flex-col items-center justify-center gap-2 overflow-hidden rounded-2xl border border-dashed px-4 py-6 text-center transition duration-150 ease-qayema focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring-accent"
            :class="
                dragging
                    ? 'border-gold-on bg-accent-wash'
                    : shownError
                      ? 'border-status-danger-border bg-field'
                      : 'border-line-strong bg-field hover:border-accent-border hover:bg-accent-wash'
            "
            @click="browse"
            @keydown.enter.prevent="browse"
            @keydown.space.prevent="browse"
            @dragover.prevent="dragging = true"
            @dragenter.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <template v-if="shownImage">
                <img :src="shownImage" alt="" class="max-h-44 w-auto rounded-control object-contain" />
                <p class="text-xs text-muted">{{ t('upload.replace') }}</p>
            </template>
            <template v-else-if="modelValue">
                <FileText class="h-8 w-8 text-accent" />
                <p class="max-w-full truncate text-sm font-medium text-fg">{{ modelValue.name }}</p>
                <p class="text-xs text-muted">{{ formatSize(modelValue.size) }}</p>
            </template>
            <template v-else>
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-hover-wash text-muted group-hover:text-accent">
                    <CloudUpload class="h-5 w-5" />
                </span>
                <p class="text-sm text-fg">{{ t('upload.prompt') }}</p>
                <p v-if="hint" class="text-xs text-muted">{{ hint }}</p>
            </template>

            <button
                v-if="modelValue"
                type="button"
                data-remove
                :aria-label="t('upload.remove')"
                :title="t('upload.remove')"
                class="absolute end-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-overlay-chip text-overlay-chip-ink transition hover:bg-overlay-chip-hover"
                @click.stop="remove"
            >
                <X class="h-4 w-4" />
            </button>

            <input
                :id="id"
                ref="input"
                type="file"
                class="sr-only"
                tabindex="-1"
                :accept="accept"
                @click.stop
                @change="onInputChange"
            />
        </div>

        <p v-if="shownError" class="mt-2 text-sm text-status-danger">{{ shownError }}</p>
    </div>
</template>
