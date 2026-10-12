<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { ChevronDown, Download, FileSpreadsheet, FileText, FolderDown, LoaderCircle } from 'lucide-vue-next';
import { useToast } from '@ahmad-chebbo/tessera-ui/Composables/useToast';

const props = defineProps({
    report: { type: String, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { t, locale } = useI18n();
const toast = useToast();
const page = usePage();

const open = ref(false);
const busy = ref(null);
const root = ref(null);

const allowed = computed(() => page.props.auth?.permissions?.includes('export reports'));

const cleanFilters = () =>
    Object.fromEntries(Object.entries(props.filters ?? {}).filter(([, value]) => value !== '' && value !== null && value !== undefined));

const firstError = (error) => {
    const errors = error.response?.data?.errors;

    return (errors ? Object.values(errors)[0]?.[0] : null) ?? error.response?.data?.message ?? t('exports.failed');
};

const start = async (format) => {
    open.value = false;
    busy.value = format;

    try {
        const { data } = await axios.post(route('exports.store'), {
            report: props.report,
            format,
            locale: locale.value,
            filters: cleanFilters(),
        });

        if (data.status === 'ready' && data.download_url) {
            toast.success(t('exports.ready'));
            window.location.assign(data.download_url);
        } else if (data.status === 'failed') {
            toast.error(data.error || t('exports.failed'));
        } else {
            toast.info(t('exports.queued'));
        }
    } catch (error) {
        toast.error(firstError(error));
    } finally {
        busy.value = null;
    }
};

const onDocumentClick = (event) => {
    if (open.value && root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div v-if="allowed" ref="root" class="relative inline-block" @keydown.esc="open = false">
        <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-hover-wash px-4 py-2.5 text-sm font-medium text-fg transition-colors hover:bg-line disabled:opacity-60"
            :disabled="busy !== null"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click="open = !open"
        >
            <LoaderCircle v-if="busy" class="h-4 w-4 animate-spin" />
            <Download v-else class="h-4 w-4" />
            {{ busy ? t('exports.preparing', { format: busy === 'xlsx' ? 'Excel' : 'PDF' }) : t('exports.button') }}
            <ChevronDown v-if="!busy" class="h-4 w-4 text-muted" />
        </button>

        <div v-if="open" role="menu" class="absolute end-0 z-30 mt-2 w-52 rounded-xl border border-line bg-surface p-1 shadow-pop">
            <button type="button" role="menuitem" data-format="xlsx" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-sm text-fg hover:bg-hover-wash" @click="start('xlsx')">
                <FileSpreadsheet class="h-4 w-4 text-status-success" />
                {{ t('exports.excel') }}
            </button>
            <button type="button" role="menuitem" data-format="pdf" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-sm text-fg hover:bg-hover-wash" @click="start('pdf')">
                <FileText class="h-4 w-4 text-status-danger" />
                {{ t('exports.pdf') }}
            </button>
            <Link :href="route('exports.index')" role="menuitem" class="mt-1 flex items-center gap-2 border-t border-line px-3 py-2 text-sm text-muted hover:text-fg">
                <FolderDown class="h-4 w-4" />
                {{ t('exports.myExports') }}
            </Link>
        </div>
    </div>
</template>
