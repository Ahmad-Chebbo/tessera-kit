<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Search, X } from 'lucide-vue-next';

const props = defineProps({
    placeholder: {
        type: String,
        default: '',
    },
    modelValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['search', 'update:modelValue']);

const { t } = useI18n();
const searchInput = ref(props.modelValue);
let debounceTimer = null;

watch(searchInput, (value) => {
    emit('update:modelValue', value);

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        emit('search', value);
    }, 300);
});

const clearSearch = () => {
    searchInput.value = '';
    emit('search', '');
};
</script>

<template>
    <div class="relative">
        <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
            <Search class="h-5 w-5 text-faint" />
        </div>
        <input
            v-model="searchInput"
            type="text"
            :placeholder="placeholder || t('common.search')"
            class="block w-full rounded-lg border border-line bg-hover-wash py-3 pe-10 ps-10 text-fg focus:border-gold-on focus:ring-ring-accent"
        />
        <button
            v-if="searchInput"
            type="button"
            @click="clearSearch"
            class="absolute inset-y-0 end-0 flex items-center pe-3 text-faint hover:text-fg"
        >
            <X class="h-5 w-5" />
        </button>
    </div>
</template>
